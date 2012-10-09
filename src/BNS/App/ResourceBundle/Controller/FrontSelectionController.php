<?php

namespace BNS\App\ResourceBundle\Controller;

use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelUserQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/selection")
 */

class FrontSelectionController extends CommonController
{	
	/**
    * Gestion de la selection de resources
    * @Route("/vue", name="BNSAppResourceBundle_front_selection_view" , options={"expose"=true})
	* @Template()
    */
	public function resourceSelectionViewAction()
	{
		return array(
			'type' => $this->getActionType(),
			'resources' => $this->getResourcesFromSelection(),
		);
	}
	
	/**
    * Gestion de la selection de resources
    * @Route("/ajout", name="BNSAppResourceBundle_front_selection" , options={"expose"=true})
	* @Template()
    */
	public function resourceSelectionAction()
	{
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		$selection = $this->get('session')->get('resource_selection');
		$currentLabel = $this->getCurrentLabelFromSession();
		
		if($currentLabel){
			$currentLabelType = $currentLabel->getType();
			$currentLabelId = $currentLabel->getId();
		}else{
			$currentLabelType = null;
			$currentLabelId = null;
		}
		if($selection == null || $this->getActionType() == "select_image"){
			$selection = array();
		}
		//Si pas dedans on ajoute, sinon on enleve
		if(!isset($selection[$resource->getId()])){
			$selection[$resource->getId()] = array(
				'label' => 	$currentLabelId,
				'label_type' => $currentLabelType					
			);
		}elseif(isset($selection[$resource->getId()])){
			unset($selection[$resource->getId()]);
		}
		
		$this->get('session')->set('resource_selection',$selection);
		
		return $this->render('BNSAppResourceBundle:FrontNavigation:resourceBlock.html.twig',
			array(
				'resource' => $resource,
				'favorites_ids' => array(),
				'selection_ids' => $this->getResourcesIdsFromSelection(),
				'is_favorite' => $resource->isFavorite($this->get('bns.right_manager')->getModelUser()->getId())	
			)
		);
	}
	
	///////   ACTIONS SUR L'ENSEMBLE DU PANIER   \\\\\\\\\\\\
	
	/**
	 * Ajout de la sélection aux favoris
	 * @Route("/ajout-favori", name="BNSAppResourceBundle_front_selection_add_to_favorite" , options={"expose"=true})
	 * @Template("BNSAppResourceBundle:FrontNavigation:contentNavigation.html.twig")
	 */
	public function resourceSelectionAddToFavoriteAction()
	{
		$singleFile = $this->getRequest()->get('singleFile') === true;
		
		if($singleFile){
			$resources = $this->getCurrentResource();
			$resource_id = $resources[0]->getId();
		}else
			$resources = $this->getResourcesFromSelection();
		
		
		$user_id = $this->get('bns.right_manager')->getModelUser()->getId();
		//@Todo sécuriser
		foreach($resources as $resource){
			$resource->toggleFavorite($user_id,true);
		}
		if($singleFile)
			return $this->forward('BNSAppResourceBundle:FrontNavigation:contentResource',array('resource_id' => $resource_id));
		else
			return $this->forward('BNSAppResourceBundle:FrontNavigation:contentNavigation',array('type' => $this->getResourceNavigationType(),'page' => null));
	}
	
	/**
    * Gestion de l'insertion des ressources
    * @Route("/inserer", name="BNSAppResourceBundle_front_selection_insert" , options={"expose"=true})
	* @Template
    */
	public function resourceSelectionInsertAction()
	{
		$resources = $this->getResourcesFromSelection();
		
		$this->killSelection();
		
		return array('resources' => $resources,'rm' => $this->get('bns.resource_manager'));
	}
	
	/**
    * Gestion de la jointure des ressources
    * @Route("/joindre", name="BNSAppResourceBundle_front_selection_join" , options={"expose"=true})
	* @Template()
    */
	public function resourceSelectionJoinAction()
	{
		$resources = $this->getResourcesFromSelection();
		
		$this->killSelection();
		
		return array('resources' => $resources, "editable" => true);
	}
		
	/**
    * Gestion de la jointure des ressources
    * @Route("/selectionner", name="BNSAppResourceBundle_front_selection_select" , options={"expose"=true})
	* @Template()
    */
	public function resourceSelectionSelectAction()
	{
		$resource = ResourceQuery::create()->findPk($this->getRequest()->get('resource_id'));
		$this->killSelection();
		return array('resources' => array($resource), "editable" => false);
	}
	
	
	
	/**
    * Vidage d'une sélection
    * @Route("/vider", name="BNSAppResourceBundle_front_selection_empty" , options={"expose"=true})
	* @Template("BNSAppResourceBundle:FrontNavigation:contentNavigation.html.twig")
    */
	public function resourceSelectionEmptyAction()
	{
		$this->killSelection();
		return $this->forward('BNSAppResourceBundle:FrontNavigation:contentNavigation',array('type' => $this->getResourceNavigationType(),'page' => null));
	}
	
	/**
    * Suppression d'une sélection
    * @Route("/supprimer", name="BNSAppResourceBundle_front_selection_delete" , options={"expose"=true})
    */
	public function resourceSelectionDeleteAction()
	{
		$resources = $this->getResourcesFromSelection();
		$rrm = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.resource_manager');
		
		foreach($resources as $resource){
			if($rrm->canDeleteResource($resource)){
				$rm->delete(
					$resource,
					$this->get('bns.right_manager')
				);
			}
		}
		
		$this->killSelection();
		return new Response();
	}
	
	/**
    * Affichage d'une alerte avant suppression
    * @Route("/supprimer-alerte", name="BNSAppResourceBundle_front_selection_delete_alert" , options={"expose"=true})
	* @Template()
    */
	public function resourceSelectionDeleteAlertAction()
	{
		$resources = $this->getResourcesFromSelection();
		$rm = $this->get('bns.resource_right_manager');
		$user = $this->get('bns.right_manager')->getModelUser();
		$authorisedResources =  array();
		foreach($resources as $resource){
			if($rm->canDeleteResource($resource)){
				$authorisedResources[] = $resource;
			}
		}
		return array('resources' => $authorisedResources);
	}
	
	/**
    * Restauration d'une sélection
    * @Route("/restaurer", name="BNSAppResourceBundle_front_selection_restore" , options={"expose"=true})
    */
	public function resourceSelectionRestoreAction()
	{
		$resources = $this->getResourcesFromSelection();
		$rrm = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.resource_manager');
		
		foreach($resources as $resource){
			if($rrm->canDeleteResource($resource)){
				$rm->restore(
					$resource,
					$this->get('bns.right_manager')
				);
			}
		}
		
		$this->killSelection();
		return new Response();
	}
	
	/**
    * Deplacement d'une sélection
    * @Route("/deplacer", name="BNSAppResourceBundle_front_selection_move" , options={"expose"=true})
    */
	public function resourceSelectionMoveAction()
	{
		$resources = $this->getResourcesFromSelection();
		
		$to = $this->getLabelFromRequest($this->getRequest());
		foreach($resources as $resource){
			$from = $this->getLabelFromSelectionKey($resource->getId());
			$resource->move($from,$to);
		}
		$this->killSelection();
		return new Response();
	}
	
	protected function getLabelFromSelectionKey($key)
	{
		$selection = $this->get('session')->get('resource_selection');
		$labelId = $selection[$key]['label'];
		$labelType = $selection[$key]['label_type'];
		if($labelType == 'user'){
			$query = ResourceLabelUserQuery::create();
		}elseif($labelType == 'group'){
			$query = ResourceLabelGroupQuery::create();
		}
		return $query->findOneById($labelId); 
	}
	
	
}