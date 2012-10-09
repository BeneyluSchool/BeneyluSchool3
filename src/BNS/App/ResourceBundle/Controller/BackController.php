<?php

namespace BNS\App\ResourceBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Route,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\Template,
	Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter,
	Symfony\Component\HttpFoundation\Response;
use BNS\App\ResourceBundle\Model\ResourceQuery,
	BNS\App\ResourceBundle\Model\ResourceInternetSearchQuery,
	BNS\App\CoreBundle\Annotation\RightsSomeWhere,
	BNS\App\CoreBundle\Annotation\Rights;

/**
 * @Route("/gestion")
 */
class BackController extends CommonController
{
    protected static $last_limit = 10;
	
	/**
    * Page d'accueil de l'administration
    * @Route("/", name="BNSAppResourceBundle_back")
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS_BACK")
    */
    public function indexAction()
    {   
		//Valeur du quota en cours
		$rm = $this->get('bns.right_manager');
		$userManager = $rm->getUserManager();		
		
		if($rm->hasRight('RESOURCE_ADMINISTRATION')){
			$userFilter =  $rm->getCurrentGroupManager()->getUsersIds();
			$canAdministrate = true;
		}else{
			$userFilter =  $rm->getUserSessionId();
			$canAdministrate = false;
		}
		//Dernières ressources envoyées
		$lastResources = ResourceQuery::create()->joinUser()->filterByUserId($userFilter)->orderByCreatedAt(\Criteria::DESC)->limit(self::$last_limit)->find();
		//Dernières recherches
		$lastResearchs = ResourceInternetSearchQuery::create()->joinUser()->filterByUserId($userFilter)->orderByCreatedAt(\Criteria::DESC)->limit(self::$last_limit)->find();
		return array(
			"group_ratio" => $rm->getCurrentGroupManager()->getResourceUsageRatio(),
			"user_ratio" => $userManager->getResourceUsageRatio(),
			'current_group_label' => $this->get('bns.right_manager')->getCurrentGroup()->getLabel(),
			'last_resources' => $lastResources,
			'last_researchs' => $lastResearchs,
			'can_administrate' => $canAdministrate
		);
    }
	
	/**
    * Page d'accueil de la personnalisation
    * @Route("/personnalisation", name="BNSAppResourceBundle_back_custom")
	* @Template()
	* @RightsSomeWhere("RESOURCE_ACCESS_BACK")
    */
	public function customAction()
	{
		$manageable_groups = $this->get('bns.right_manager')->getGroupsWherePermission('RESOURCE_ADMINISTRATION');
		$canAdministrate = $this->get('bns.right_manager')->hasRight('RESOURCE_ADMINISTRATION');
		return array("manageable_groups" => $manageable_groups,'can_administrate' => $canAdministrate);
	}
	
	/**
    * Page de gestion de la liste blanche
    * @Route("/personnalisation/liste-blanche", name="BNSAppResourceBundle_back_custom_white_list")
	* @Template()
	* @Rights("RESOURCE_ADMINISTRATION")
    */
	public function customWhiteListAction()
	{

		$reM = $this->get('bns.resource_manager'); 
		$riM = $this->get('bns.right_manager');
		
		$contextId = $riM->getCurrentGroupId();
		//Récupération des ressources "liens"
		$links = $reM->search(null,null,array($contextId),array('types' => array('LINK')));
		//Récupération de la white list du group
		$whiteList = $reM->getWhiteList($contextId);
		
		return array('links' => $links,'whiteList' => $whiteList,'can_administrate' => $this->get('bns.right_manager')->hasRight('RESOURCE_ADMINISTRATION'));
	}
	
	/**
	 * Activation / désactivation des items de la whiteList
	 * @Route("/personnalisation/liste/blanche/lien", name="BNSAppResourceBundle_white_list_toggle", options={"expose"=true}))
	 * @Template("BNSAppResourceBundle:Back:whiteListBlock.html.twig")
	 * @Rights("RESOURCE_ADMINISTRATION")
	 */
	public function whiteListToggleAction()
	{
		$reM = $this->get('bns.resource_manager'); 
		$riM = $this->get('bns.right_manager');
		$contextId = $riM->getCurrentGroupId();
		
		$link = ResourceQuery::create()->findOneById($this->getRequest()->get('resource_id'));
		$status = $reM->toggleWhiteList($link->getId(),$contextId);
		return array('link' => $link,'status' => $status);	
	}
	
	/**
    * Page d'accueil des abonnements
    * @Route("/abonnements", name="BNSAppResourceBundle_back_catalog")
	* @Template()
	* @Rights("RESOURCE_ADMINISTRATION")
    */
	public function catalogAction()
	{
		return array();	
	}	
	
	/**
    * Page de visualisation et d'activation de la white liste générale
    * @Route("/personnalisation/liste-blanche-generale", name="BNSAppResourceBundle_back_custom_white_list_general")
	* @Template()
	* @Rights("RESOURCE_ADMINISTRATION")
    */
	public function customWhiteListGeneralAction()
	{
		$whiteListGeneral = unserialize($this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST'));
		$whiteListUse = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST_USE_PARENT');
		return array(
			'white_list_general' => $whiteListGeneral,
			'white_list_use' => $whiteListUse,
			'can_administrate' => $this->get('bns.right_manager')->hasRight('RESOURCE_ADMINISTRATION')
		);
	}
	
	/**
    * Toggle de l'utilisation de la white liste générale
    * @Route("/personnalisation/liste-blanche-generale-changement", name="BNSAppResourceBundle_back_custom_white_list_general_toggle")
	* @Rights("RESOURCE_ADMINISTRATION")
    */
	public function customWhiteListGeneralToggleAction()
	{
		$whiteListGeneral = $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('WHITE_LIST_USE_PARENT');
		$whiteListGeneral = $whiteListGeneral == true ? 0 : 1;
					
		$this->get('bns.right_manager')->getCurrentGroupManager()->setAttribute('WHITE_LIST_USE_PARENT',$whiteListGeneral);
		
		//Mise à jour de la clé pour la cache Google
		$this->get('bns.resource_manager')->updateUniqueKey($this->get('bns.right_manager')->getCurrentGroup()->getId());
		
		return $this->redirect($this->generateUrl('BNSAppResourceBundle_back_custom_white_list_general'));
	}
	
	
}
