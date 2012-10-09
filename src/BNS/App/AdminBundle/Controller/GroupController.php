<?php

namespace BNS\App\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Form\Type\GroupType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Criteria;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @Route("/groupes")
 */

class GroupController extends Controller
{
	/**
	 * Page d'accueil de la gestion des groupes;
	 * tous les groupes sont listés dans un tableau 
	 * 
	 * @Route("/", name="BNSAppAdminBundle_group", options={"expose"=true})
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		//On filtre par type de groupe
   		if($this->getRequest()->get('type')){
			$this->get('session')->set('admin_group_list_type',$this->getRequest()->get('type'));
		}else{
			$this->get('session')->remove('admin_group_list_type');
		}
		return array('groupTypes' => GroupTypeQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->find());
    }
	
	/**
	 * Page de création d'un groupe
	 * @Route("/creer", name="BNSAppAdminBundle_group_add")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function addAction()
    {
   		$group = new Group();
				
		$form = $this->createForm(new GroupType(),$group);
				
		if ($this->getRequest()->getMethod() == 'POST')
		{
			$form->bindRequest($this->getRequest());
			if ($form->isValid())
			{
				$gm = $this->get('bns.group_manager');
				$groupParams = array(
					'type' => $group->getGroupType()->getType(),
					'group_type_id' => $group->getGroupTypeId(),
					'label' => $group->getLabel(),
					'domain_id' => $this->container->getParameter('domain_id')
				);
				$gm->createGroup($groupParams);
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_group'));
			}
		}
		return array('form' => $form->createView());
    }
	
    /**
     * Accueil de la fiche groupe : liste des utilisateurs
     * 
	 * @param     $group objet de type Group que l'on récupère grâce au ParamConverter de Propel (il retrouve l'objet grâce au slug du groupe)
	 *
     * @Route("/fiche/{id}", name="BNSAppAdminBundle_group_sheet_index")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetIndexAction($id)
    {	
        $group = GroupQuery::create()->findOneById($id);
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		
    	return array('group' => $group,'group_manager' => $gm,'page' => 'index');
    }
	
	/**
	 * @param     $group objet de type Group que l'on récupère grâce au ParamConverter de Propel (il retrouve l'objet grâce au slug du groupe)
	 *
     * @Route("/update-parent/{group_id}", name="BNSAppAdminBundle_group_update_parent")
     * @Template()
	 */
	public function updateParentAction($group_id)
	{
		$groupManager = $this->get('bns.group_manager')->setGroupById($group_id);
		
		if ($this->getRequest()->getMethod() == 'POST')
		{
			if($this->getRequest()->get('group_parent_id') != null){
				$groupManager->updateParent($this->getRequest()->get('group_parent_id'));
			}
		}
		return $this->redirect($this->generateUrl('BNSAppAdminBundle_group_sheet_index',array('id' => $group_id)));
	}
	
	
	/**
     * Paramètres du groupe, au sein de la fiche groupe
     * 
	 * @param     $group objet de type Group que l'on récupère grâce au ParamConverter de Propel (il retrouve l'objet grâce au slug du groupe)
	 *
     * @Route("/fiche/parametres/{slug}", name="BNSAppAdminBundle_group_sheet_params")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetParamsAction($slug)
    {	
        $group = GroupQuery::create()->findOneBySlug($slug);
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		
    	return array('group' => $group,'group_manager' => $gm,'page' => 'params');
    }
	
	/**
     * Page détaillant les liens concernant le groupe $group
     * 
	 * @param     $group objet de type Group que l'on récupère grâce au ParamConverter de Propel (il retrouve l'objet grâce au slug du groupe)
	 *
     * @Route("/fiche/liens/{slug}", name="BNSAppAdminBundle_group_sheet_links")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetLinksAction($slug)
    {	
        $group = GroupQuery::create()->findOneBySlug($slug);
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		
    	return array('group' => $group,'group_manager' => $gm,'page' => 'links');
    }
	
	/**
     * Page détaillant les règles concernant le groupe $group
     * 
	 * @param $group objet de type Group que l'on récupère grâce au ParamConverter de Propel (il retrouve l'objet grâce au slug du groupe)
     * @Route("/fiche/regles/{slug}", name="BNSAppAdminBundle_group_sheet_rules")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
    public function sheetRulesAction($slug)
    {	
        $group = GroupQuery::create()->findOneBySlug($slug);
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		
		$rules = $gm->getRules();

		//Optimisation des requettages sur la centrale
		$ruleDatas = array();
		foreach($rules as $rule){
			if(!isset($ruleDatas['group'][$rule['who_group_id']])){
				$ruleDatas['group'][$rule['who_group_id']] = $gm->getSafeGroup($rule['who_group_id']);
			}
			if(isset($rule['rule_where']['group_type_id'])){
				if(!isset($ruleDatas['group_type'][$rule['rule_where']['group_type_id']])){
					$ruleDatas['group_type'][$rule['rule_where']['group_type_id']] = $gm->getSafeGroupType($rule['rule_where']['group_type_id']);
				}
			}
			if(!isset($ruleDatas['group'][$rule['rule_where']['group_id']])){
				$ruleDatas['group'][$rule['rule_where']['group_id']]= $gm->getSafeGroup($rule['who_group_id']);
			}
		}
    	return array('group' => $group,'rules' => $rules,'page' => 'rules','ruleDatas' => $ruleDatas);
    }
	
	/**
     * Bascule d'une règle  
     * 
	 * @param int $status Statut commandé à la centrale
	 *
     * @Route("/fiche/regles-bascule/{id}/{status}", name="BNSAppAdminBundle_group_rule_toggle")
     * @Template("BNSAppAdminBundle:Group:ruleToggle.html.twig")
	 * @Rights("ADMIN_ACCESS")
     */
    public function toggleRuleAction($id,$status)
    {
		$rule = $this->get('bns.rule_manager')->editRule(array('id' => $id,'state' => $status == '1' ? true : false));
		return array('rule' => $rule);
	}
	
	/**
     * Suppression d'une règle  
     * 
	 * @param int $status Statut commandé à la centrale
	 *
     * @Route("/fiche/regles-suppression/{id}/{groupSlug}", name="BNSAppAdminBundle_group_rule_delete")
	 * @Rights("ADMIN_ACCESS")
     */
    public function deleteRuleAction($id,$groupSlug)
    {
		$this->get('bns.rule_manager')->deleteRule($id);
		return $this->redirect($this->generateUrl('BNSAppAdminBundle_group_sheet_rules',array('slug' => $groupSlug)));
	}
		 
    /**
     * @param Request $request
     *
     * @return Response
     * 
     * @Route("/liste", name="BNSAppAdminBundle_group_list")
	 * @Rights("ADMIN_ACCESS")
     */
    public function getAjaxGroupsAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
    		throw new NotFoundHttpException();
    	}
    	$dataTables = $this->get('datatables');
		
		$query = GroupQuery::create();
		
		$filterType = $this->get('session')->get('admin_group_list_type');
		
		if($this->get('session')->get('admin_group_list_type')){
			$query
				->useGroupTypeQuery()
					->useGroupTypeI18nQuery()
						->filterBySlug($filterType)
					->endUse()
				->endUse();
		}
		
    	$responses  = $dataTables->execute($query, $request, array(
    		GroupPeer::LABEL,
    		GroupPeer::GROUP_TYPE_ID,
    		GroupPeer::SLUG
    	));
    
    	foreach ($dataTables->getResults() as $key => $group)
    	{
    		$responses['aaData'][$key][] = $group->getLabel();
    		$responses['aaData'][$key][] = $group->getGroupType()->getLabel();
    		// TODO : Solution temporaire très sale !
    		$link = '
    		<a href="' . $this->generateUrl('BNSAppAdminBundle_group_sheet_index',array('id' => $group->getId())) .'" title="Voir sa fiche">
    			<img src="/medias/images/icons/fugue/magnifier-left.png" alt="Voir sa fiche" />
    		</a>';
    		$responses['aaData'][$key][] = $link;
    	}
    	return new Response(json_encode($responses));
    }
	
	/** Edition des attributs d'un groupe
	 * 
	 * @Route("/parametres/formulaire", name="BNSAppAdminBundle_group_attribute_form" , options={"expose"=true})
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function paramFormAction()
    {
   		$attributeUniqueName = $this->getRequest()->get('attribute_unique_name');
		$groupId = $this->getRequest()->get('group_id');
		$dataTemplate = GroupTypeDataTemplateQuery::create()->findOneByUniqueName($attributeUniqueName);
		$group = GroupQuery::create()->findOneById($groupId);
		
		$value = $this->getRequest()->get('value');
		
		if($value != null){
			$group->setAttribute($attributeUniqueName,$value);
			$render = 'value';
		}else{
			$render = 'form';
		}
		
		$value = $group->getAttribute($attributeUniqueName);
		
		$type = $dataTemplate->getType();
		$collectionArray = array();
		
		switch($type){
			case "SINGLE": 
			case "TEXT":
				$collection = null;
			break;
			case "ONE_CHOICE":
			case "MULTIPLE_CHOICE":
				$collection = GroupTypeDataChoiceQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findByGroupTypeDataTemplateUniqueName($dataTemplate->getUniqueName());
				if(is_array($value)){
					foreach($value as $val){
						$collectionArray[] = $val;
					}
				}else{
					$collectionArray[] = $value;
				}
			break;
		
		}
		
		return array(
			'value' => $value,
			'type' => $type ,
			'collection' => $collection,
			'attributeUniqueName' => $attributeUniqueName,
			'group' => $group,
			'collectionArray' => $collectionArray,
			'render' => $render
		);
	}
}