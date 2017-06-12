<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery,
	BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;

use BNS\App\ResourceBundle\Model\ResourceQuery,
	Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Template,
	Symfony\Component\HttpFoundation\Response;

class CommonController extends Controller
{
	//////  Fonctions liées à la resource en cours  \\\\\\\\
	protected function setCurrentResource($resourceId)
	{
		$this->get('session')->set('resource_current_resource',$resourceId);
	}

	protected function getCurrentResource()
	{
		$resource = ResourceQuery::create()->findOneById($this->get('session')->get('resource_current_resource'));
		if (null != $resource) {
			return $resource;
		}

		return false;
	}

	/////  Fonctions liées à la "sélection" => panier \\\\\\

	/*
	 * Récupération des Ids des resources "panier"
	 * @return array() Tableau d'entiers
	 */
	protected function getResourcesIdsFromSelection()
	{
		$session = $this->get('session')->get('resource_selection');
		$selection = is_array($session) ? $session : array();

		return array_keys($selection);
	}

	/**
	 * Récupération de la sélection en resources resources"panier"
	 *
	 * @return ResourceCollection
	 */
	protected function getResourcesFromSelection()
	{
		return ResourceQuery::create()->findById($this->getResourcesIdsFromSelection());
	}

	/**
	 * @param Resource $resource
	 *
	 * @return null|ResourceLabel
	 */
	protected function getResourceLabelFromSelection($resource)
	{
		$selection = $this->get('session')->get('resource_selection');

		if (!isset($selection[$resource->getId()])) {
			return null;
		}

		$label = $selection[$resource->getId()];
		if ($label['label_type'] == 'user') {
			$query = ResourceLabelUserQuery::create('rla');
		}
		else {
			$query = ResourceLabelGroupQuery::create('rla');
		}

		return $query->findPk($label['label']);
	}

	/*
	 * Suppression de la sélection
	 */
	protected function clearSelection()
	{
		$this->get('session')->remove('resource_selection');
		$this->get('session')->remove('resource_label_resources');
	}

	/**
	 * Suppression des filtres de nav
	 */
	protected function clearFilters()
	{
		$this->get('session')->remove('resource_favorite_filter');
		$this->get('session')->remove('resource_filter');
		$this->get('session')->remove('resource_display');
	}



	///////   Focntions liées à la récupération des labels  \\\\\\\

	/*
	 * Renvoie le label selon la request (et les paramètres label_id / type)
	 *
	 * @param $request Request
	 *
	 * @return ResourceLabel|boolean False if not found
	 */
	protected function getLabelFromRequest()
	{
		$request = $this->getRequest();
		$rightManager = $this->get('bns.right_manager');
		$resourceRightManager = $this->get('bns.resource_right_manager');

		// Label parameters
		$slug = $request->get('slug');

		if (null != $slug) {
			// Start with user label
			$label = ResourceLabelUserQuery::create('lu')
				->where('lu.Slug = ?', $slug)
			->findOne();

			// If user label is NULL, search if it's a group label
			if (null == $label) {
				$label = ResourceLabelGroupQuery::create('lg')
					->joinWith('lg.Group g')
					->where('lg.Slug = ?', $slug)
				->findOne();

				// Not found
				if (null == $label) {
					return false;
				}
			}

			// Redirect if user has not the permission to read the resource folder
			if (!$resourceRightManager->setUser($this->getUser())->canReadLabel($label)) {
				if ($rightManager->isAdult()) {
					$message = "Vous n'avez pas le droit d'accéder au dossier \"" . $label->getLabel() . "\"";
				}
				else {
					$message = "Tu n'as pas le droit d'accéder au dossier \"" . $label->getLabel() . "\"";
				}

				$this->get('session')->getFlashBag()->add('error', $message);

				return false;
			}

			return $label;
		}

		return false;
	}

	/*
	 * Renvoie le label en cours à partir de la session (stocké sous la forme type-label_id)
	 *
	 * @return Label
	 */
	protected function getCurrentLabelFromSession()
	{
		if (!$this->get('session')->has('resource_current_label')) {
			return false;
		}

		return $this->getLabelFromPattern($this->get('session')->get('resource_current_label'));
	}

	/**
	 * @param ResourceLabel $label
	 */
	public function setCurrentLabelIntoSession($label)
	{
		$this->get('session')->set('resource_current_label', $this->getLabelPattern($label));
	}

	/**
	 *
	 */
	public function clearCurrentLabelIntoSession()
	{
		$this->get('session')->remove('resource_current_label');
	}

	/**
	 * @param ResourceLabel $label
	 *
	 * @return string
	 */
	protected function getLabelPattern($label)
	{
		return $label->getType() . '_' . $label->getObjectLinkedId() . '_' . $label->getId();
	}

	///////////  Fonctions liées aux droits  \\\\\\\\\\\\\

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

	///////////  Fonctions liées au type de navigation  \\\\\\\\\\\\\\

	/*
	 * Choix : search || insert || join || select_image
	 */

	protected function getActionType()
	{
		return $this->get('session')->get('resource_action_type');
	}

	protected function setActionType($type)
	{
		return $this->get('session')->set('resource_action_type',$type);
	}

	///////////  Fonctions liées au type de navigation dans les ressources \\\\\\\\\\\\\\

	/*
	 * Choix : ressources | corbeille | favories (français car en URL)
	 */

	protected function getResourceNavigationType()
	{
		$value = $this->get('session')->get('resource_navigation_type');
		if ($value == null) {
			$value = 'ressources';
			$this->setResourceNavigationType($value);
		}

		return $value;
	}

	protected function setResourceNavigationType($type)
	{
		$this->get('session')->set('resource_navigation_type', $type);
	}

	protected function killResourceNavigationType()
	{
        $this->get("stat.resource")->newSearch();
		$this->get('session')->remove('resource_navigation_type');
	}

	/**
	 * Redirect the user to homepage if direct http access, or return empty response
	 *
	 * @return Response
	 */
	protected function redirectHome()
	{
		if ($this->getRequest()->isXmlHttpRequest()) {
			return new Response();
		}

		return $this->redirect($this->generateUrl('BNSAppResourceBundle_front'));
	}

    //// TEMPORAIRE pour catalogue
    protected function canHaveCatalog()
    {
        if($this->container->hasParameter('store.activate_catalog'))
        {
            return $this->container->getParameter('store.activate_catalog');
        }
        return false;
    }




	/**
	 * @param string $reference
	 */
	public function setCallBackReference($reference)
	{
		$this->get('session')->set('resource_reference', $reference);
	}

	/**
	 * @return string The DOM#id for the AJAX append data callback when join button is called
	 */
	public function getCallBackReference()
	{
		return $this->get('session')->get('resource_reference');
	}

	/**
	 * @param string $view
	 * @param array $params
	 *
	 * @return Response
	 */
	public function renderHistory($view, array $params = array(), $insertDockBar = true)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			$this->killResourceNavigationType();

			$params = array_merge($params, array(
				'historyView'	=> $view,
				'insertDockBar'	=> $insertDockBar,
				'objectiveContext' => null,
				'select_img_id'   => $this->get('session')->get('resource_select_image_final_id'),
				'select_callback' => $this->get('session')->get('resource_select_image_callback'),
			));

			$view = 'BNSAppResourceBundle:History:history.html.twig';
		}

		return $this->render($view, $params);
	}

	/**
	 * @param array $item ['resource_id', 'label_id', 'label_type']
	 *
	 * @return null|Resource
	 */
	protected function getResourceFromSelection($resourceId, $labelType, $labelId, $isFromSelection = false)
	{
		$query = ResourceQuery::create('r')
			->where('r.Id = ?', $resourceId)
		;

		if ($labelType == 'user') {
			$query->joinWith('r.ResourceLinkUser rl')
				  ->joinWith('rl.ResourceLabelUser rla')
			;
		}
		else {
			$query->joinWith('r.ResourceLinkGroup rl')
				  ->joinWith('rl.ResourceLabelGroup rla')
				  ->joinWith('rla.Group g')
			;
		}

		$resource = $query->where('rl.Status = ?', $isFromSelection)
			  ->where('rla.Id = ?', $labelId)
		->findOne();

		return $resource;
	}

    /**
     * @return int 
     */
    public function getGroupContext()
    {
        return $this->get('session')->has('resource_group_context', $this->get('bns.right_manager')->getCurrentGroupId());
    }

    /**
     * @param int $groupId
     */
    public function setGroupContext($groupId)
    {
        $this->get('session')->set('resource_group_context', $groupId);
    }

    /**
     * 
     */
    public function clearGroupContext()
    {
        $this->get('session')->remove('resource_group_context');
    }

    /**
     * @param string $message
     *
     * @return Response
     */
    public function renderError($message = 'Une erreur est survenue, veuillez réessayer.')
    {
        return $this->render('BNSAppResourceBundle:Front:error.html.twig', array(
            'message' => $message
        ));
    }
}
