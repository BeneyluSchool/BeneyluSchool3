<?php

namespace BNS\App\WorkshopBundle\Controller;

use BNS\App\ResourceBundle\Form\Type\ResourceType;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUser;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use BNS\App\ResourceBundle\Model\ResourceLinkGroup;
use BNS\App\ResourceBundle\Model\ResourceLinkUser;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\WorkshopBundle\Form\Type\WorkshopDocumentType;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentInput;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentTemplateQuery;
use BNS\App\WorkshopBundle\Model\WorkshopResourceLabel;
use BNS\App\WorkshopBundle\Model\WorkshopResourceLabelQuery;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FrontController extends Controller
{

    /**
     * TODO remove this
     * @deprecated
     *
     * @Template("BNSAppWorkshopBundle:Front:index.html.twig")
     * @RightsSomeWhere("WORKSHOP_ACCESS")
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * Temporary route for the viewer
     * TODO rename/remove it
     *
     * @Route("/poc-viewer")
     * @Template()
     * @RightsSomeWhere("WORKSHOP_ACCESS")
     */
    public function viewerAction()
    {
        return array();
    }

    /**
     * @Route("/document/{id}/export")
     * @RightsSomeWhere("WORKSHOP_ACCESS")
     * @param WorkshopDocument $document
     * @return Response
     */
    public function documentExportAction(WorkshopDocument $document)
    {
        $session = $this->get('session');
        $router = $this->get('router');
        $filename = $document->getLabel();

        // build angular app route
        $url = $router->getContext()->getScheme() . '://'
            . $router->getContext()->getHost()
            . $router->getContext()->getBaseUrl()
            . '/app/?embed=1#/workshop/documents/'
            . $document->getId()
            . '/export'
        ;

        $session->save();
        session_write_close();
        $result = $this->get('knp_snappy.pdf')->getOutput($url, array(
            'margin-top' => 0,
            'margin-right' => 0,
            'margin-bottom' => 0,
            'margin-left' => 0,
            'debug-javascript' => false,
            'javascript-delay' => 5000,
            'window-status' => 'done',
            'cookie' => array(
                $session->getName() => $session->getId(),
            ),
        ));

        return new Response(
            $result,
            200,
            array(
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            )
        );
    }

    /**
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
     */
    public function indexAction2(Request $request)
    {
		$form = $this->createForm(new ResourceType());
		if ($request->isMethod('POST')) {
			$form->bind($request);
            if($form->getData()->getLabel() != "")
            {
                if ($form->isValid()) {
                    $parameters = $request->get('workshop_document_form');

                    $resource = $form->getData();
                    $resource->setUserId($this->getUser()->getId());
                    $resource->setLang($this->getUser()->getLang());
                    $resource->setTypeUniqueName($parameters['template_unique_name'] == 'DOCUMENT' ? 'ATELIER_DOCUMENT': 'ATELIER_DOCUMENT');
                    $resource->setFilename($resource->getLabel());
                    $resource->setFileMimeType('atelier/document');
                    $resource->save();

                    $label = $this->getLabelFromPattern($request->get('label'));

                    /*if ($parameters['template_unique_name'] == 'DOCUMENT') {

                    }
                    else { // MULTIMEDIA_NOTEBOOK
                        $label = WorkshopResourceLabelQuery::create('wrl')
                            ->joinWith('wrl.ResourceLabelUser rlu')
                            ->where('wrl.UserId = ?', $this->getUser()->getId())
                        ->findOne();

                        if (null == $label) {
                            $root = ResourceLabelUserQuery::create('rlu')->findRoot($this->getUser()->getId());
                            if (null == $root) {
                                throw new \RuntimeException('No resource label user root found for user : ' . $this->getUser()->getId());
                            }

                            $label = new ResourceLabelUser();
                            $label->setLabel('Mes cahiers multimédia');
                            $label->setUserId($this->getUser()->getId());
                            $label->insertAsLastChildOf($root);
                            $label->save();

                            $workshopLabel = new WorkshopResourceLabel();
                            $workshopLabel->setUserId($this->getUser()->getId());
                            $workshopLabel->setLabelUserId($label->getId());
                            $workshopLabel->setType('MULTIMEDIA_NOTEBOOK');
                            $workshopLabel->save();
                        }
                        else {
                            $label = $label->getResourceLabelUser();
                        }
                    }*/

                    // Label resource saving process
                    if ($label instanceof ResourceLabelGroup) {
                        $link = new ResourceLinkGroup();
                        $link->setResourceLabelGroupId($label->getId());
                    }
                    else { // ResourceLabelUser
                        $link = new ResourceLinkUser();
                        $link->setResourceLabelUserId($label->getId());
                    }

                    $link->setResourceId($resource->getId());
                    $link->setIsStrongLink(true);
                    $link->save();

                    // Document saving process
                    $document = new WorkshopDocument();
                    $document->setResourceId($resource->getId());
                    $document->setTemplateUniqueName($parameters['template_unique_name']);

                    $formDoc = $this->createForm(new WorkshopDocumentType(WorkshopDocumentTemplateQuery::create('wdt')->findPk($document->getTemplateUniqueName())), $document);
                    $formDoc->bind($request);

                    if ($formDoc->isValid()) {
                        $formDoc->getData()->save();

                        foreach ($formDoc->getData()->getWorkshopDocumentInputs() as $i => $input) {
                            $this->get('bns.resource_manager')->bindAttachments($input, $this->getRequest(), $i);
                            $this->get('bns.resource_manager')->saveAttachments($input, $this->getRequest(), null, $i);
                        }

                        $this->get('session')->getFlashBag()->add('success', 'Le document a été créé avec succès !');

                        return $this->redirect($this->generateUrl('workshop_visualisation', array(
                            'slug' => $resource->getSlug()
                        )));
                    }
                }
            }else{
                $this->get('session')->getFlashBag()->add('error', 'Veuillez saisir un titre pour votre document');
            }
		}
		
        return $this->render('BNSAppWorkshopBundle:Front:index.html.twig', array(
			'form'				=> $form->createView(),
			'documentTemplates' => WorkshopDocumentTemplateQuery::create('wdt')->find()
		));
    }
	
	/**
	 * @Route("/gabarit/{slug}", name="workshop_template_choice")
	 * 
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
	 */
	public function templateChoiceAction($slug, $form = null, $canDeletePage = false)
	{
		if (null == $form) {
			$template = WorkshopDocumentTemplateQuery::create('wdt')
				->where('wdt.Slug = ?', $slug)
			->findOne();

			if (null == $template) {
				return $this->templateChoicesAction();
			}

			$document = new WorkshopDocument();
			$document->setTemplateUniqueName($template->getUniqueName());

			foreach ($template->getWorkshopDocumentTemplateInputs() as $inputTemplate) {
				$input = new WorkshopDocumentInput();
				$input->setInputUniqueName($inputTemplate->getUniqueName());

				$document->addWorkshopDocumentInput($input);
			}

			$form = $this->createForm(new WorkshopDocumentType($template), $document);
		}
		else {
			$template = $form->getData()->getWorkshopDocumentTemplate();
		}
		
		return $this->render('BNSAppWorkshopBundle:TemplateForm:template_form_' . strtolower($template->getUniqueName()) . '.html.twig', array(
			'form'			=> $form->createView(),
			'canDeletePage' => $canDeletePage
		));
	}
	
	/**
	 * @return Response
	 */
	private function redirectHome()
	{
		return $this->redirect($this->generateUrl('BNSAppWorkshopBundle_front'));
	}
	
	/*
	 * Verification des droits de création dans la destination
	 *
	 * @param : string $destination : triplet type_groupIdOUuserId_labelId
	 */
	protected function getLabelFromPattern($destination)
	{
		$destination = explode('_', $destination);
		$this->get('bns.right_manager')->forbidIf(count($destination) != 3);

		// user ou group
		$destination_type = $destination[0];
		// userId ou groupId
		$destination_object_id = $destination[1];
		// LabelId
		$destination_label_id = $destination[2];

		$rightManager = $this->get('bns.right_manager');
		$resourceRightManager = $this->get('bns.resource_right_manager')->setUser($rightManager->getUserSession());

		$rightManager->forbidIf(!in_array($destination_type, array(
			'user',
			'group'
		)));

		if ($destination_type == 'group') {
			$label = ResourceLabelGroupQuery::create()->findOneById($destination_label_id);
		}
		elseif ($destination_type == 'user') {
			$label = ResourceLabelUserQuery::create()->findOneById($destination_label_id);
		}

		$rightManager->forbidIf(!$resourceRightManager->canReadLabel($label));

		return $label;
	}
	
	/**
	 * @Route("/visualisation/{slug}", name="workshop_visualisation")
	 * 
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
	 */
	public function visualizeAction($slug)
	{
		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $slug)
		->findOne();
		
		if (null == $resource) {
			$this->get('session')->getFlashBag()->add('error', 'Le document n\'existe pas.');
			
			return $this->redirectHome();
		}
		
		$documents = WorkshopDocumentQuery::create('wd')
			->joinWith('wd.WorkshopDocumentInput wdi')
			->joinWith('wdi.WorkshopDocumentTemplateInput wdti')
			->where('wd.ResourceId = ?', $resource->getId())
			->orderBy('wd.Id')
			->orderBy('wdi.Id')
		->find();
		
		if (count($documents) == 0) {
			$this->get('session')->getFlashBag()->add('error', 'Le type de document est introuvable.');
			
			return $this->redirectHome();
		}
		
		$resource->replaceWorkshopDocuments($documents);
		
		return $this->render('BNSAppWorkshopBundle:Front:visualisation.html.twig', array(
			'resource'  => $resource,
			'canManage'	=> $this->get('bns.resource_right_manager')->canManageResource($resource)
		));
	}
	
	/**
	 * @param Resource $document
	 */
	public function renderTemplateAction($document)
	{
		return $this->render('BNSAppWorkshopBundle:Template:template_' . strtolower($document->getTemplateUniqueName()) . '.html.twig', array(
			'document' => $document
		));
	}
	
	/**
	 * @Route("/editer/{slug}", name="workshop_edit")
	 * 
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
	 */
	public function editAction($slug)
	{
		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $slug)
		->findOne();
		
		if (null == $resource) {
			$this->get('session')->getFlashBag()->add('error', 'Le document n\'existe pas.');
			
			return $this->redirectHome();
		}
		
		// Security process
		if (!$this->get('bns.resource_right_manager')->canManageResource($resource)) {
			if ($this->get('bns.user_manager')->isAdult()) {
				$this->get('session')->getFlashBag()->add('error', 'Vous n\'avez pas le droit de modifier ce document.');
			}
			else {
				$this->get('session')->getFlashBag()->add('error', 'Tu n\'as pas le droit de modifier ce document.');
			}
			
			return $this->redirectHome();
		}
		
		$documents = WorkshopDocumentQuery::create('wd')
			->joinWith('wd.WorkshopDocumentTemplate wdt')
			->joinWith('wd.WorkshopDocumentInput wdi')
			->joinWith('wdi.WorkshopDocumentTemplateInput wdti')
			->where('wd.ResourceId = ?', $resource->getId())
			->orderBy('wd.Id')
			->orderBy('wdi.Id')
		->find();
		
		if (count($documents) == 0) {
			$this->get('session')->getFlashBag()->add('error', 'Le type de document est introuvable.');
			
			return $this->redirectHome();
		}
		
		$resource->replaceWorkshopDocuments($documents);
		
		$formResource = $this->createForm(new ResourceType(), $resource);
		$formDocuments = array();
		
		foreach ($resource->getWorkshopDocuments() as $i => $document) {
			$type = new WorkshopDocumentType($document->getWorkshopDocumentTemplate());
			$type->setName('workshop_document_form_' . ($i + 1));
			
			$formDocuments[] = $this->createForm($type, $document);;
		}
		
		if ($this->getRequest()->isMethod('POST')) {
			$formResource->bind($this->getRequest());
			if ($formResource->isValid()) {
				$formResource->getData()->save();
				
				foreach ($formDocuments as $document) {
					$document->bind($this->getRequest());
					if ($document->isValid()) {
						$document->getData()->save();
						
						foreach ($document->getData()->getWorkshopDocumentInputs() as $input) {
							$this->get('bns.resource_manager')->bindAttachments($input, $this->getRequest(), $input->getId());
							$this->get('bns.resource_manager')->saveAttachments($input, $this->getRequest(), null, $input->getId());
						}
					}
				}
				
				$this->get('session')->getFlashBag()->add('success', 'Le document a été enregistré avec succès !');
				
				return $this->redirect($this->generateUrl('workshop_visualisation', array(
					'slug' => $resource->getSlug()
				)));
			}
		}
		
		return $this->render('BNSAppWorkshopBundle:Front:edit.html.twig', array(
			'resource'		=> $resource,
			'formResource'  => $formResource->createView(),
			'formDocuments' => $formDocuments
		));
	}
	
	/**
	 * @Route("/cahier-multimedia/{slug}/ajouter-une-page", name="workshop_multimedia_notebook_add_page")
	 * 
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
	 */
	public function addPageAction($slug)
	{
		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $slug)
		->findOne();
		
		if (null == $resource) {
			$this->get('session')->getFlashBag()->add('error', 'Le document n\'existe pas.');
			
			return $this->redirectHome();
		}
		
		// Security process
		if (!$this->get('bns.resource_right_manager')->canManageResource($resource)) {
			if ($this->get('bns.user_manager')->isAdult()) {
				$this->get('session')->getFlashBag()->add('error', 'Vous n\'avez pas le droit de modifier ce document.');
			}
			else {
				$this->get('session')->getFlashBag()->add('error', 'Tu n\'as pas le droit de modifier ce document.');
			}
			
			return $this->redirectHome();
		}
		
		$documents = WorkshopDocumentQuery::create('wd')
			->joinWith('wd.WorkshopDocumentTemplate wdt')
			->where('wd.ResourceId = ?', $resource->getId())
			->orderBy('wd.Id')
		->find();
		
		if (count($documents) == 0) {
			$this->get('session')->getFlashBag()->add('error', 'Le type de document est introuvable.');
			
			return $this->redirectHome();
		}
		
		$resource->replaceWorkshopDocuments($documents);
		
		$newDocument = new WorkshopDocument();
		$newDocument->setResourceId($resource->getId());
		$newDocument->setTemplateUniqueName($documents[0]->getTemplateUniqueName());
		$newDocument->save();
		
		foreach ($documents[0]->getWorkshopDocumentTemplate()->getWorkshopDocumentTemplateInputs() as $templateInput) {
			$input = new WorkshopDocumentInput();
			$input->setDocumentId($newDocument->getId());
			$input->setInputUniqueName($templateInput->getUniqueName());
			$input->save();
		}
		
		return $this->redirect($this->generateUrl('workshop_edit', array(
			'slug' => $slug
		)));
	}
	
	/**
	 * @Route("/cahier-multimedia/{slug}/supprimer-une-page/{id}", name="workshop_multimedia_notebook_delete_page")
	 * 
	 * @RightsSomeWhere("WORKSHOP_ACCESS")
	 */
	public function deletePageAction($slug, $id)
	{
		$resource = ResourceQuery::create('r')
			->where('r.Slug = ?', $slug)
		->findOne();
		
		if (null == $resource) {
			$this->get('session')->getFlashBag()->add('error', 'Le document n\'existe pas.');
			
			return $this->redirectHome();
		}
		
		// Security process
		if (!$this->get('bns.resource_right_manager')->canManageResource($resource)) {
			if ($this->get('bns.user_manager')->isAdult()) {
				$this->get('session')->getFlashBag()->add('error', 'Vous n\'avez pas le droit de modifier ce document.');
			}
			else {
				$this->get('session')->getFlashBag()->add('error', 'Tu n\'as pas le droit de modifier ce document.');
			}
			
			return $this->redirectHome();
		}
		
		$document = WorkshopDocumentQuery::create('wd')
			->where('wd.ResourceId = ?', $resource->getId())
			->where('wd.Id = ?', $id)
		->findOne();
		
		if (null == $document) {
			$this->get('session')->getFlashBag()->add('error', 'La page est introuvable.');
			
			return $this->redirectHome();
		}
		
		// Finally
		$document->delete();
		$this->get('session')->getFlashBag()->add('success', 'La page a été supprimée avec succès !');
		
		return $this->redirect($this->generateUrl('workshop_edit', array(
			'slug' => $slug
		)));
	}
}
