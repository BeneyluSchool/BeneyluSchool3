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
use BNS\App\ResourceBundle\Model\ResourceFavoritesQuery;
use BNS\App\ResourceBundle\Form\Type\ResourceType;

/**
 * @Route("/navigation")
 */

class FrontNavigationController extends CommonController
{
	/**
    * Sidebar de la navigation front des ressources
    * @Route("/barre", name="BNSAppResourceBundle_front_navigation_sidebar" , options={"expose"=true})
	* @Template()
    */
	public function sidebarAction()
	{		
		$rightManager = $this->get('bns.right_manager');
		$user_label = $rightManager->getModelUser()->getResourceLabelRoot();
		//Les dossiers de mes groupes
		$group_labels = ResourceLabelGroupQuery::create()->filterByGroupId($rightManager->getGroupIdsWherePermission("RESOURCE_ACCESS"))->findRoots();
		
		$newLabel = $this->getLabelFromRequest($this->getRequest());
		
		if($newLabel){
			$this->killFilters();
			$type = $this->getRequest()->get('type');
			$this->get('session')->set('resource_current_label',$type . '-' . $newLabel->getId());
			if($newLabel->getType() == "group"){
				if($newLabel->isUserFolder()){
					$this->get('session')->set('resource_current_user_folder_id',$newLabel->getId());
				}
			}else{
				//$this->get('session')->remove('resource_current_user_folder_id');
			}
		}
		
		$currentLabel = $this->getCurrentLabelFromSession();
		$usersFolder = false;
		
		if(!$currentLabel){
			return array(
				"user_label" => $user_label,
				"group_labels" => $group_labels,
				'root_level' => true
			);	
		}else{
			
			//Recuperation de l'arbre en entier
			switch($currentLabel->getType()){
				case 'user':
					$query = ResourceLabelUserQuery::create();
					$scopeId = $currentLabel->getUserId();
				break;
				case 'group':
					$query = ResourceLabelGroupQuery::create();
					$scopeId = $currentLabel->getGroupId();
				break;
			}
			
			$root = $query->findRoot($scopeId);		
			//On remonte les "ancêtres"	
			$testedLabel = $currentLabel;
			$selection_ids = array();
			
			while($testedLabel->hasParent()){
				$testedLabel = $testedLabel->getParent();
				$selection_ids[] = $testedLabel->getId();
			}

			return array(
				'type' => $currentLabel->getType(),
				'current_label' => $currentLabel,
				'user_label' => $user_label,
				'group_labels' => $group_labels,
				'selection_ids' => $selection_ids,
				'root' => $root,
				'users_foler' => $usersFolder
			);			
		}		
	}
	
	/**
	 * 
	 *  
	 * 
	 */
	protected function initContentNavigation($params)
	{
		if(!isset($params['page']))
			$params['page'] = 0;
		
		if($params['page'] == 0){
			if($this->get('session')->has('resource_navigation_page')){
				$params['page'] = $this->get('session')->get('resource_navigation_page');
			}else
				$params['page'] = 1;
		}
		
		$this->get('session')->set('resource_navigation_page',$params['page']);
		
		if(!isset($params['limit']))
			$params['limit'] = 25;
				
		if(isset($params['need_label']))
			if($params['need_label'] == true){
				$cur_label = $this->getCurrentLabelFromSession();
				if($cur_label){
					$params['current_label'] = $cur_label;
					$params['current_label_id'] = $cur_label->getId();
				}
			}
		if(!isset($params['current_label'])){
			$params['current_label'] = null;
			$params['current_label_id'] = null;
		}
		
		if(isset($params['force_favorites']))
			if($params['force_favorites'] == true)
				$params['favorite_filter'] = true;
		if(!isset($params['favorite_filter']))
			$params['favorite_filter'] = $this->get('session')->get('resource_favorite_filter');
		
		$params['filters'] = $this->get('session')->get('resource_filter');
		$params['user_id'] = $this->get('bns.right_manager')->getUserSessionId();
		
		//Tri
		$sort = $this->get('session')->get('resource_sort');
		if($sort)
			$params['sort'] = $sort;
		else
			$params['sort'] = "alpha";
		
		if($this->getActionType() == "select_image"){
			$params['filters'][] = 'IMAGE';
		}
		
		$params['resources'] = ResourceQuery::getResources($params);
		
		$resources_ids = array();
		
		foreach($params['resources'] as $resource){
			$resources_ids[] = $resource->getId();
		}
		//Optimisation pour force favorites possible
		$favorites = ResourceFavoritesQuery::create()->filterByResourceId($resources_ids)->find();
		
		$favs = array();
		
		foreach($favorites as $fav){
			$favs[] = $fav->getResourceId();
		}
		
		$params['favorites_ids'] = $favs;
		$params['selection_ids'] = $this->getResourcesIdsFromSelection();
		
		//Affichage
		$display = $this->get('session')->get('resource_display');
		
		if($display == null || $display == "block")
			$params['display'] = "block";
		else
			$params['display'] = "list";
		
		return $params;
	}
	
	/**
    * Contenu de la navigation front des ressources
    * @Route("/contenu/navigation/{type}/{page}", name="BNSAppResourceBundle_front_navigation_content" , defaults={"page" = "0","type" = "undefined"} ,options={"expose"=true})
	* @Template("")
    */
	public function contentNavigationAction($type= "undefined",$page = 0)
	{ 
		if($this->getResourceNavigationType() == "garbage" && $type != "garbage"){
			$this->killSelection();
		}
			
		if($type == "undefined"){
			$type = $this->getResourceNavigationType();
		}
		
		//Si corbeille on vide le panier
		if($type == "garbage"){
			$this->killSelection();
		}
		
		$this->setResourceNavigationType($type);
		
		switch($this->getResourceNavigationType()){
			case "ressources":
				return $this->initContentNavigation(
					array(
						'page' => $page,
						'need_label' => true,
						'force_favorites' => false,
						'type' => $type
					)
				);	
			break;
			case "favoris":
				return $this->initContentNavigation(
					array(
						'page' => $page,
						'need_label' => false,
						'force_favorites' => true,
						'type' => $type
					)
				);
			break;
			case "garbage":
				return $this->initContentNavigation(
					array(
						'page' => $page,
						'need_label' => true,
						'force_favorites' => false,
						'type' => $type,
					)
				);
			break;
			case "recherche":
				
				if($this->getRequest()->get('q') != null)
					$this->setCurrentSearch($this->getRequest()->get('q'));
				
				return $this->initContentNavigation(
					array(
						'page' => $page,
						'need_label' => false,
						'type' => $type,
						'query' => $this->getCurrentSearch()
					)
				);
			break;
		}	
	}

	/**
    * Contenu de la navigation front des ressources
    * @Route("/contenu/ressource", name="BNSAppResourceBundle_front_navigation_content_resource" , options={"expose"=true})
	* @Template()
    */
	public function contentResourceAction()
	{
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		$rm = $this->get('bns.resource_manager');
		$rm->setObject($resource);
		$this->setCurrentResource($resource->getId());
		return array('resource' => $resource,'rm' => $rm);
	}
	
	/**
    * Edition d'une ressource
    * @Route("/contenu/ressource/editer/{resource_id}", name="BNSAppResourceBundle_front_navigation_content_resource_edit" , options={"expose"=true})
	* @Template()
    */
	public function contentResourceEditAction()
	{
		$request = $this->getRequest();
		
		$resource = ResourceQuery::create()->findOneById($request->get('resource_id'));
		$rm = $this->get('bns.resource_manager');
		$rm->setObject($resource);
		$form = $this->createForm(new ResourceType($resource), $resource);
		
		if ($request->getMethod() == 'POST') {
			
			$form->bindRequest($request);
			
			if ($form->isValid()) {
				
				// perform some action, such as saving the task to the database
				$resource->save();
				return $this->render('BNSAppResourceBundle:FrontNavigation:contentResource.html.twig',
					array(
						'resource' => $resource,'rm' => $rm
					)
				);
			}
		}
		return array(
			'resource' => $resource,
			'form' => $form->createView(),
			'tmp_labels' => array()
		);
	}
	
	/**
    * Ajout d'un label à une ressource
    * @Route("/contenu/ressource/ajouter-libelle", name="BNSAppResourceBundle_front_navigation_content_resource_add_label" , options={"expose"=true})
	* @Template("BNSAppResourceBundle:FrontNavigation:contentResourceEditLabels.html.twig")
    */
	public function contentResourceAddLabelAction()
	{
		$label = $this->getLabelFromRequest($this->getRequest());
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		//TODO : Sécuriser
		$resource->linkLabel($label->getType(),null,$label->getId(),false);
		
		return array('resource' => $resource);
		
	}
	
	/**
    * Suppression d'un label à une ressource
    * @Route("/contenu/ressource/supprimer-libelle", name="BNSAppResourceBundle_front_navigation_content_resource_delete_label" , options={"expose"=true})
	* @Template("BNSAppResourceBundle:FrontNavigation:contentResourceEditLabels.html.twig")
    */
	public function contentResourceDeleteLabelAction()
	{
		$label = $this->getLabelFromRequest($this->getRequest());
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		//TODO : Sécuriser
		$resource->unlinkLabel($label->getType(),$label->getId());
		
		return array('resource' => $resource);
		
	}
	
	
	/**
    * Gestion des filtres en sidebar
    * @Route("/filtre", name="BNSAppResourceBundle_front_navigation_filter_type" , options={"expose"=true})
	* @Template()
    */
	public function filterTypeAction()
	{
		$resource_types = $this->get('bns.resource_manager')->getTypes();
		
		$type = $this->getRequest()->get('type');
		
		if($this->get('session')->has('resource_filter')){
			$filter_type = $this->get('session')->get('resource_filter');
		}else{
			$filter_type = array();
		}
		
		if($type != null && in_array($type, $resource_types)){
			$filter_type = $this->get('session')->get('resource_filter');
			
			if($filter_type == null)
				$filter_type = array();
			
			if(!in_array($type,$filter_type)){
				$filter_type[] = $type;
			}elseif(in_array($type,$filter_type)){
				$key = array_keys($filter_type,$type);
				$key = $key[0];
				unset($filter_type[$key]);
			}
			
			$this->get('session')->set('resource_filter',$filter_type);
		}
		
		$filters = array();
		
		foreach($resource_types as $resource_type){
			$filters[] = array(
				'type' => $resource_type,
				'is_active' => in_array($resource_type, $filter_type)
			);
		}
		
		$favorite_filter = $this->get('session')->get('resource_favorite_filter');
		if($favorite_filter == null) $favorite_filter = false;
		
		if($type == "favorite"){
			$favorite_filter = !$favorite_filter;
			$this->get('session')->set('resource_favorite_filter',$favorite_filter);
		}
		
		//Gestion des tri
		if($type == "sort-alpha"){
			$this->get('session')->set('resource_sort',"alpha");
		}
		
		if($type == "sort-chrono"){
			$this->get('session')->set('resource_sort',"chrono");
		}
		
		if($this->get('session')->get('resource_sort') == null){
			$sort = "alpha";
		}else{
			$sort = $this->get('session')->get('resource_sort');
		}
		
		//Gestion de l'affichage
		$display = $this->get('session')->get('resource_display');
		
		if($type == "toggle-view"){
			if($display == null || $display == "block")
				$display = "list";
			else
				$display = "block";
			$this->get('session')->set('resource_display',$display);
		}
		
		return array('filters' => $filters,'favorite_filter' => $favorite_filter, 'sort' => $sort, "display" => $display);	
		
	}
	
	/**
    * Gestion de la selection de resources
    * @Route("/favori", name="BNSAppResourceBundle_front_navigation_favorite" , options={"expose"=true})
    */
	public function resourceFavoriteAction()
	{
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		$resource_dir = $this->container->getParameter('resource_files_dir');
		$isFavorite = $resource->toggleFavorite($this->get('bns.right_manager')->getModelUser()->getId());
		
		return $this->render('BNSAppResourceBundle:FrontNavigation:resourceBlock.html.twig',
			array(
				'resource' => $resource,
				'favorites_ids' => array(),
				"selection_ids" => $this->getResourcesIdsFromSelection(),
				'is_favorite' => $isFavorite,
				'resource_dir' => $resource_dir,
				'selection' => $this->get('session')->get('resource_selection'),
			)
		);
	}
	
	/**
    * Page d'accueil des ressources
    * @Route("/retour", name="BNSAppResourceBundle_front_navigation_back_link")
	*/
	public function backLinkAction()
	{
		$currentLabel = $this->getCurrentLabelFromSession();
		return $this->forward('BNSAppResourceBundle:Front:showCategory',array('type' => $currentLabel->getType(),'label_id' => $currentLabel->getId()));
	}
	
	/**
    * Déplacement d'une ressource
    * @Route("/deplacer", name="BNSAppResourceBundle_front_navigation_move_resource" , options={"expose"=true})
	*/
	public function moveResourceAction(){
		$to = $this->getLabelFromRequest($this->getRequest());
		$resource = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		
		$resource->move($this->getCurrentLabelFromSession(),$to);
		
		return new Response();
		
	}
}