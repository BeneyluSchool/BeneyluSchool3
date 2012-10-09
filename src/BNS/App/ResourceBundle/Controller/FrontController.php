<?php

namespace BNS\App\ResourceBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use BNS\App\ResourceBundle\Model\ResourceQuery,
	BNS\App\CoreBundle\Model\GroupQuery,
	BNS\App\CoreBundle\Annotation\RightsSomeWhere,
	BNS\App\CoreBundle\Annotation\Rights,
	BNS\App\ResourceBundle\BNSResourceManager;
use \Videopian;


/**
 * @Route("/")
 */

class FrontController extends CommonController
{
   
	/**
    * Page d'accueil des ressources si accès depuis la dockbar (page vierge - seulement illustration - et chargement de l'Iframe)
    * @Route("accueil/{type}", name="BNSAppResourceBundle_front", defaults={"type" = "search"})
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @param $type : pour la gestion des actions possibles (savoir si on parcourt ou si on insert : none || insert || join || select_image)
    */
    public function frontAction($type)
    {
        $this->setActionType($type);
		return array('type' => $type,'resourceId' => 'none');
    }
	
	/**
    * Page d'accueil des ressources
    * @Route("index/{resourceId}", name="BNSAppResourceBundle_home" , defaults={"resourceId" = "none"})
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template()
    */
    public function indexAction($resourceId = "none")
    {
        //Selection du type de navigation
		$this->get('session')->remove('resource_current_label');
		$this->get('session')->remove('resource_navigation_page');
		$this->killResourceNavigationType();
		$this->killCurrentSearch();
		return array('resourceId' => $resourceId);
    }
	
	/**
    * Redirection vers une categorie
    * @Route("categorie/{type}/{label_id}", name="BNSAppResourceBundle_show_category" , options={"expose"=true})
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template("BNSAppResourceBundle:Front:index.html.twig")
    */
    public function showCategoryAction()
    {
		$label = $this->getLabelFromRequest($this->getRequest());
		$this->get('session')->set('resource_current_label',$label->getType() . '-' . $label->getId());
        //Selection du type de navigation
		return array('current_label' => $label);
    }
	
	/**
    * Page d'accueil
    * @Route("accueil-contenu", name="BNSAppResourceBundle_front_index_content")
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template()
    */
	public function indexContentAction(){		
		//Envoi de l'Url du XML d'annotations pourle moteur de recherche Google
		switch($this->get('kernel')->getEnvironment()){
			case "app_dev":
			case "app_test":
			//	$whiteListUrl = "https://www.beneyluschool.net/search/512bc16df28e3d9df605cb12ce1ee62284747523/annotations";
			//break;
			case "app_prod":
				$key = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('WHITE_LIST_UNIQUE_KEY');
				//Si clé non initialisée, on l'initialise
				if($key == null || $key == ""){
					$this->get('bns.resource_manager')->updateUniqueKey($this->get('bns.right_manager')->getCurrentGroup()->getId());
					$key = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('WHITE_LIST_UNIQUE_KEY');
				}
				$whiteListUrl =  $this->get('router')->generate('BNSAppResourceBundle_white_list_xml',array('key' => $key),true);
			break;				
		}
		return array('white_list_url' => $whiteListUrl);
	}
	
	/**
    * Page d'accueil de l'administration
    * @Route("iframe/{type}/{reference}", name="BNSAppResourceBundle_call_iframe" , options={"expose"=true}, defaults={"reference" = "none", "resourceId" = "none" })
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @param $type : action possible (insert || navigation || join)
    */
    public function callIframeAction($type = "search",$reference = "none",$resourceId = "none")
    {
		if($reference != 'none'){
			$this->getRequest()->getSession()->set('resource_reference',$reference);
		}
		
		$this->setActionType($type);
		return array('type' => $type,'resourceId' => $resourceId);
    }
	
	/**
    * Page de call de la sélection d'une image
    * @Route("selection/image/{final_id}/{callback}", name="BNSAppResourceBundle_front_select_image_caller" , options={"expose"=true})
	* @RightsSomeWhere("RESOURCE_ACCESS")
    */
    public function selectImageCallerAction($final_id,$callback)
    {
        $this->get('session')->set('resource_select_image_final_id',$final_id);
		$this->get('session')->set('resource_select_image_callback',$callback);
		return $this->forward('BNSAppResourceBundle:Front:callIframe',array('type' => 'select_image'));
    }
	
	/**
    * Page pour voir directement une resource depuis son Id
    * @Route("voir/{resourceId}", name="BNSAppResourceBundle_front_view" , options={"expose"=true})
	* @RightsSomeWhere("RESOURCE_ACCESS")
    */
    public function frontViewAction($resourceId)
    {
        return $this->forward('BNSAppResourceBundle:Front:callIframe',array('type' => 'resource_view','resourceId' => $resourceId,'reference' => 'none'));
    }
	
	
	/**
    * Page de téléchargement des fichiers des ressources
	* Attention : avoir XsendFile d'installé
	* @param $size Taille si fichier sizeable
	* @param $resource_slug slug de la ressource
    * @Route("telecharger/{resource_slug}/{size}", name="BNSAppResourceBundle_download" , defaults={"size" = "original" })
	* @RightsSomeWhere("RESOURCE_ACCESS")
    */
	public function downloadAction($resource_slug,$size){
		
		$resource = ResourceQuery::create()->findOneBySlug($resource_slug);
		$rim = $this->get('bns.right_manager');
		$rm = $this->get('bns.resource_manager');
		$rm->setObject($resource);
		
		$resourceRightManager = $this->get('bns.resource_right_manager');

		//Vérification des droits
		$resourceRightManager->setUser($rim->getUserSession());
		$rim->forbidIf(!$resourceRightManager->canReadResource($resource));

		$response = new Response();
		
		if($resource->isActive()){
			$response->headers->set('X-Sendfile', $rm->getAbsoluteFilePath());
			$response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $resource->getFilename()));
			$response->headers->set('Content-Type', $resource->getFileMimeType());
		}else{
			
			$response->headers->set('X-Sendfile', $rm->getDeletedImage());
			$response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', "DocumentSupprime.png"));
			$response->headers->set('Content-Type','image/png');
			
		}
		
		$response->setStatusCode(200);
		return $response;
	}
		
	/**
    * Page de recherche de ressources
    * @Route("recherche", name="BNSAppResourceBundle_search" , options={"expose"=true})
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS")
    */
	public function searchAction()
	{
		//Recupération de la query
		$string_query = $this->getRequest()->get('q');
		
		$rm = $this->get('bns.right_manager');
		
		//On lance la recherche
		$resources = $this->get('bns.resource_manager')->search(
			$string_query,
			$rm->getModelUser()->getId(),
			$rm->getGroupIdsWherePermission('RESOURCE_ACCESS')
		);
		
		return array(
			'string_query' => $string_query,
			'resources' => $resources
		);
	}
	
	/**
	 * Callback lors d'une recherche sur le moteur Google
	 * @Route("recherche-internet/{label}", name="BNSAppResourceBundle_search_add" , options={"expose"=true})
	 * @RightsSomeWhere("RESOURCE_ACCESS")
	 */
	public function searchAddAction($label)
	{
		$userId = $this->get('bns.right_manager')->getUserSession()->getId();
		$this->get('bns.resource_manager')->addSearchInternet($label,$userId);
		return new Response();
	}
	
	/**
    * Barre de boutons du dessus
    * @Route("barre-outil", name="BNSAppResourceBundle_toolbar" , options={"expose"=true})
	* @param String $page Page sur laquelle afficher la barre d'outils
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template()
    */
	public function toolbarAction($page)
	{
		$tools = array();
		$navType = $this->get('session')->get('resource_action_type');
		//Croix pour fermer ou non
		if(in_array($navType,array('join','insert','select_image'))){
			$tools["close"] = true;
		}
		//Selon la page
		switch($page){
			case "home":
				$tools["add_resources"] = true;
				if($navType == "select_image"){
					$tools["title"] = "Sélection d'une image";
				}else{
					$tools["my_quota"] = array();
					$gm = $this->get('bns.right_manager')->getCurrentGroupManager();
					$userManager = $this->get('bns.right_manager')->getUserManager();
					$tools["my_quota"]['percentage'] = $userManager->getResourceUsageRatio();
					if(0 <= $tools["my_quota"]['percentage'] && $tools["my_quota"]['percentage'] < 50)
						$tools["my_quota"]['class'] = 'success';
					elseif(50 <= $tools["my_quota"]['percentage'] && $tools["my_quota"]['percentage'] < 75)
						$tools["my_quota"]['class'] = 'warning';
					elseif(75 <= $tools["my_quota"]['percentage'])
						$tools["my_quota"]['class'] = 'danger';
				}
			break;
			case "add-choose":
			case "add-files":
			case "add-urls":
				$tools["back_link"] = true;
			break;	
		}
		return array('tools' => $tools);
	}
	
	/**
    * XML de la white list pour un groupe donnée
    * @Route("white-list/{key}", name="BNSAppResourceBundle_white_list_xml" , options={"expose"=true})
	* @param String $key Clé unique identifiant
	* @Template()
    */
	public function whiteListXmlAction($key)
	{		
		$reM = $this->get('bns.resource_manager'); 
		$group = GroupQuery::create()->filterBySingleAttribute("WHITE_LIST_UNIQUE_KEY",$key)->findOne();
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		if($gm->getAttribute('WHITE_LIST_USE_PARENT') == true){
			$parentWhiteList = unserialize($gm->getAttribute('WHITE_LIST'));
		}else{
			$parentWhiteList = array();
		}
		$links = $reM->getWhiteListObjects($group->getId());
		$response = new Response();
		$response = $this->render('BNSAppResourceBundle:Front:whiteListXml.html.twig', array('links' => $links,'parent_white_list' => $parentWhiteList));
		$response->headers->set('Content-Type', 'text/xml');
        return $response;
	}
	
	/**
	* Action de vidage de la corbeille : chaque fichier de la corbeille pas à supprimer définitivement
	* 
	* @Route("vider-corbeille", name="BNSAppResourceBundle_front_garbage_empty" , options={"expose"=true})
	* @RightsSomeWhere("RESOURCE_ACCESS")
	* @Template()
	 */
	public function garbageEmptyAction()
	{
		$user = $this->get('bns.right_manager')->getModelUser();
		$rrm = $this->get('bns.resource_right_manager');
		$rm = $this->get('bns.resource_manager');
		$rrm->setUser($user);
		$resources = ResourceQuery::create()
			->filterByStatusDeletion(BNSResourceManager::STATUS_GARBAGED)
			->filterByUserId($user->getId())
			->find();
		foreach($resources as $resource){
			if($rrm->canDeleteResource($resource)){
				$rm->delete($resource);
			}
		}
		return $this->forward('BNSAppResourceBundle:FrontNavigation:contentNavigation',array('type' => 'garbage','page' => 0));
	}
}
