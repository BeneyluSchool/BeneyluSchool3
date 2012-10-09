<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery,
	BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;

use BNS\App\ResourceBundle\Model\ResourceQuery,
	Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Template,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter,
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
		if($resource)
			return $resource;
		return false;
	}
	
	/////  Fonctions liées à la "sélection" => panier \\\\\\
	
	/*
	 * Récupération des Ids des resources "panier"
	 * @return array() Tableau d'entiers
	 */
	protected function getResourcesIdsFromSelection(){
		$session = $this->get('session')->get('resource_selection');
		$selection = is_array($session) ? $session : array();
		return array_keys($selection);
	}
	
	/*
	 * Récupération de la sélection en resources resources"panier"
	 * @return ResourceCollection
	 */
	protected function getResourcesFromSelection(){
		$selection = $this->getResourcesIdsFromSelection();
		if(!is_array($selection)){
			$selection = array();
		}
		return ResourceQuery::create()->findById($selection);
	}
	
	/*
	 * Suppression de la sélection
	 */
	protected function killSelection(){
		$this->get('session')->remove('resource_selection');
	}
	
	/**
	 * Suppression des filtres de nav
	 */
	protected function killFilters(){
		$this->get('session')->remove('resource_filter');
	}
	
	
	
	///////   Focntions liées à la récupération des labels  \\\\\\\
	
	/*
	 * Renvoie le label selon la request (et les paramètres label_id / type)
	 * @param $request Request
	 * @return Label
	 */
	protected function getLabelFromRequest($request)
	{
		$rightManager = $this->get('bns.right_manager');
		$resourceRightManager = $this->get('bns.resource_right_manager');
		
		if($request->get('label_id') && $request->get('type')){
			$label_id = $request->get('label_id');
			$type = $request->get('type');
			if($type == 'user'){
				$current_label = ResourceLabelUserQuery::create()->findOneById($label_id);
			}elseif($type == 'group'){
				$current_label = ResourceLabelGroupQuery::create()->findOneById($label_id);
			}
			
			$resourceRightManager->setUser($rightManager->getUserSession());
			
			$rightManager->forbidIf(!$resourceRightManager->canReadLabel($current_label));
			
			return $current_label;
		}else{
			return false;
		}
	}
	
	/*
	 * Renvoie le label en cours à partir de la session (stocké sous la forme type-label_id)
	 * @return Label
	 */
	protected function getCurrentLabelFromSession()
	{
		if(!$this->get('session')->has('resource_current_label')){
			return false;
		}
		
		$current = $this->get('session')->get('resource_current_label');
		
		$ex = explode('-', $current);
						
		$type = $ex[0];
		$label_id = $ex[1];
		
		if($type == 'user'){
			$current_label = ResourceLabelUserQuery::create()->findOneById($label_id);
		}elseif($type == 'group'){
			$current_label = ResourceLabelGroupQuery::create()->findOneById($label_id);
		}
		return $current_label;
	}
	
	///////////  Fonctions liées aux droits  \\\\\\\\\\\\\
	
	/*
	 * Verification des droits de création dans la destination
	 * @param : string $destination : triplet type_groupIdOUuserId_labelId
	 */
	protected function checkDestinationPermission($destination)
	{
		$destination = explode('_',$destination);
		$this->get('bns.right_manager')->forbidIf(count($destination) != 3);

		//user ou group
		$destination_type = $destination[0];
		//userId ou groupId
		$destination_object_id = $destination[1];
		//LabelId
		$destination_label_id = $destination[2];
		$resourceRightManager = $this->get('bns.resource_right_manager');
		$rightManager = $this->get('bns.right_manager');
		$resourceRightManager->setUser($rightManager->getUserSession());
		
		
		$rightManager->forbidIf(!in_array($destination_type,array('user','group')));

		if($destination_type == 'group'){
			$label = ResourceLabelGroupQuery::create()->findOneById($destination_label_id);
		}elseif($destination_type == 'user'){
			$label = ResourceLabelUserQuery::create()->findOneById($destination_label_id);
		}
		$rightManager->forbidIf(!$resourceRightManager->canCreateResource($label));
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
		if($value == null){
			$value = 'ressources';
			$this->setResourceNavigationType($value);
		}
		return $value;
	}
	
	protected function setResourceNavigationType($type)
	{
		$this->get('session')->set('resource_navigation_type',$type);
	}
	
	protected function killResourceNavigationType()
	{
		$this->get('session')->remove('resource_navigation_type');
	}
	
	///////////  Fonctions liées à la recherche en cours  \\\\\\\\\\\\\\
	
	
	protected function getCurrentSearch()
	{
		return $this->get('session')->get('resource_current_search');
	}
	
	protected function setCurrentSearch($search)
	{
		$this->get('session')->set('resource_current_search',$search);
	}
	
	protected function killCurrentSearch()
	{
		$this->get('session')->remove('resource_current_search');
	}
	
	
}
