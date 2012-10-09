<?php

namespace BNS\App\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\FormError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use BNS\App\RegistrationBundle\Model\SchoolInformationPeer;
use BNS\App\RegistrationBundle\Form\Type\SchoolCreationType;


/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/informations-ecoles")
 */
class SchoolInformationController extends Controller
{
	/**
	 * @Route("/", name="admin_school_information")
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return $this->render('BNSAppAdminBundle:SchoolInformation:index.html.twig');
    }
		 
    /**
     * @Route("/liste", name="admin_school_information_list")
	 * @Rights("ADMIN_ACCESS")
     */
    public function listAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
    		throw new NotFoundHttpException('This page expects AJAX header !');
    	}
		
    	$dataTables	= $this->get('datatables');
		$query		= SchoolInformationQuery::create();
    	$response	= $dataTables->execute($query, $this->getRequest(), array(
    		SchoolInformationPeer::NAME,
    		SchoolInformationPeer::UAI,
    		SchoolInformationPeer::ADDRESS,
    		SchoolInformationPeer::CITY,
    		SchoolInformationPeer::ZIP_CODE,
    		SchoolInformationPeer::STATUS
    	));
    
    	foreach ($dataTables->getResults() as $key => $schoolInfo)
    	{
    		$response['aaData'][$key][] = $schoolInfo->getName();
    		$response['aaData'][$key][] = $schoolInfo->getUai();
    		$response['aaData'][$key][] = $schoolInfo->getAddress();
    		$response['aaData'][$key][] = $schoolInfo->getCity();
    		$response['aaData'][$key][] = $schoolInfo->getZipCode();
			
			$status = '';
			switch ($schoolInfo->getStatus()) {
				case SchoolInformationPeer::STATUS_PENDING_VALIDATION:
					$status = 'En attente';
				break;
			
				default:
					$status = 'Validé';
				break;
			}
			
    		$response['aaData'][$key][] = $status;
			
    		// TODO : Solution temporaire très sale !
    		$link = '
    		<a href="' . $this->generateUrl('admin_school_information_show', array('id' => $schoolInfo->getId())) .'" title="Voir sa fiche">
    			<img src="/medias/images/icons/fugue/magnifier-left.png" alt="Voir sa fiche" />
    		</a>';
    		$response['aaData'][$key][] = $link;
    	}
    	return new Response(json_encode($response));
    }
	
	/**
	 * @Route("/fiche/{id}", name="admin_school_information_show")
	 * @Rights("ADMIN_ACCESS")
	 */
	public function showAction($id)
	{
		$schoolInfo = SchoolInformationQuery::create('si')
			->joinWith('Group', \Criteria::LEFT_JOIN)
		->findPk($id);
		
		if (null == $schoolInfo) {
			throw new NotFoundHttpException('The school information with id : ' . $id . ' is NOT found !');
		}
		
		return $this->render('BNSAppAdminBundle:SchoolInformation:show.html.twig', array(
			'schoolInfo' => $schoolInfo
		));
	}
	
	/**
	 * @Route("/fiche/{id}/editer", name="admin_school_information_edit")
	 * @Rights("ADMIN_ACCESS")
	 */
	public function editAction($id)
	{
		$schoolInfo = SchoolInformationQuery::create('si')->findPk($id);
		
		if (null == $schoolInfo) {
			throw new NotFoundHttpException('The school information with id : ' . $id . ' is NOT found !');
		}
		
		$form = $this->createForm(new SchoolCreationType(), $schoolInfo);
		if ($this->getRequest()->isMethod('POST')) {
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()) {
				$schoolInfo = $form->getData();
				/* @var $schoolInfo \BNS\App\RegistrationBundle\Model\SchoolInformation */
				if ($schoolInfo->getCountry() == 'FR' && null == $schoolInfo->getUai()) {
					// UAI obligatoire pour la France
					$form->get('uai')->addError(new FormError('Vous devez renseigner votre code UAI !'));
				}
				else {
					$schoolInfo->save();
					
					return $this->redirect($this->generateUrl('admin_school_information_show', array(
						'id' => $schoolInfo->getId()
					)));
				}
			}
		}
		
		return $this->render('BNSAppAdminBundle:SchoolInformation:edit.html.twig', array(
			'schoolInfo'	=> $schoolInfo,
			'form'			=> $form->createView()
		));
	}
	
	/**
	 * @Route("/fiche/{id}/valider", name="admin_school_information_validate")
	 * @Rights("ADMIN_ACCESS")
	 */
	public function validateAction($id)
	{
		$schoolInfo = SchoolInformationQuery::create('si')->findPk($id);
		
		if (null == $schoolInfo) {
			throw new NotFoundHttpException('The school information with id : ' . $id . ' is NOT found !');
		}
		
		// Validating school
		$schoolInfo->setStatus(SchoolInformationPeer::STATUS_VALIDATED);
		$schoolInfo->save();
		
		// Checking language
		$lang = 'fr';
		if (in_array(strtolower($schoolInfo->getCountry()), $this->container->getParameter('available_languages'))) {
			$lang = strtolower($schoolInfo->getCountry());
		}
		
		// Sending email to school director
		$this->get('bns.mailer')->send('SCHOOL_CREATED', array(
			'school_name'		=> $schoolInfo->getName(),
			'registration_url'	=> $this->generateUrl('registration_free', array(), true)
		),
		$schoolInfo->getEmail(),
		$lang);
		
		return $this->redirect($this->generateUrl('admin_school_information_show', array(
			'id' => $schoolInfo->getId()
		)));
	}
}