<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\GroupBundle\Form\Model\EditGroupFormModel;
use BNS\App\GroupBundle\Form\Type\EditGroupType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\CoreBundle\Form\Type\GroupSimpleType;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;

class BackController extends Controller
{
	/**
	 * @Route("/sidebar", name="BNSAppGroupBundle_sidebar")
	 * @Template()
	 */
	public function sidebarAction()
	{
		return array(
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes()
		);
	}
	
	
	/**
	 * @Route("/", name="BNSAppGroupBundle_back")
	 * @Template()
	 */
	public function indexAction()
    {	
		$group = $this->get('bns.right_manager')->getCurrentGroup();
		$request = $this->getRequest();
		$form = $this->createForm(new EditGroupType(), new EditGroupFormModel($group));
		if ($request->isMethod('POST')) {
			$form->bindRequest($request);
			//TODO checker validité form
			$form->getData()->save();
			return $this->redirect($this->generateUrl('BNSAppGroupBundle_back'));
		}
		$homeMessage = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_MESSAGE');
		
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
    	
		return array(
			'form' => $form->createView(),
			'homeMessage'     => $homeMessage,
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
			'group' => $group,
			'rules' => $rules,
			'page' => 'rules',
			'ruleDatas' => $ruleDatas
		);
    }
	
	
	/**
     * Bascule d'une règle  
     * 
	 * @param int $status Statut commandé à la centrale
	 *
     * @Route("/fiche/regles-bascule/{id}/{status}", name="BNSAppGroupBundle_group_rule_toggle")
     * @Template("BNSAppGroupBundle:Back:ruleToggle.html.twig")
     */
    public function toggleRuleAction($id,$status)
    {
		$rule = $this->get('bns.rule_manager')->editRule(array('id' => $id,'state' => $status == '1' ? true : false));
		
		$this->get('bns.right_manager')->getCurrentGroupManager()->clearGroupCache();
		
		return $this->redirect($this->generateUrl('BNSAppGroupBundle_back'));
	}
	
	/**
	 * @Route("/liste/{type}", name="BNSAppGroupBundle_group_list")
	 * @Template()
	 */
	public function groupListAction($type)
	{
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight(strtoupper($type) . '_CREATE'));
		$groupType = GroupTypeQuery::create()->joinWithI18n($rm->getLocale())->findOneByType(strtoupper($type));
		$cgm = $rm->getCurrentGroupManager();
		return array(
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
			'groupType' => $groupType,
			'groups'	=> $cgm->getSubgroups(true,false,$groupType->getId())
		);
	}
	
	/**
	 * @Route("/creer/{type}", name="BNSAppGroupBundle_group_add")
	 * @Template()
	 */
	public function groupAddAction($type)
	{
		$rm = $this->get('bns.right_manager');
		$rm->forbidIf(!$rm->hasRight(strtoupper($type) . '_CREATE'));
		$groupType = GroupTypeQuery::create()->joinWithI18n($rm->getLocale())->findOneByType(strtoupper($type));
		$cgm = $rm->getCurrentGroupManager();
		
		$group = new Group();
				
		$form = $this->createForm(new GroupSimpleType(),$group);
				
		if ($this->getRequest()->getMethod() == 'POST')
		{
			$form->bindRequest($this->getRequest());
			if ($form->isValid())
			{
				$gm = $this->get('bns.group_manager');
				$groupParams = array(
					'type' => $groupType->getType(),
					'group_type_id' => $groupType->getId(),
					'label' => $group->getLabel(),
					'domain_id' => $this->container->getParameter('domain_id')
				);
				$group = $gm->createGroup($groupParams);
				$groupManager = $this->get('bns.group_manager');
				$groupManager->setGroup($group);
				$groupManager->updateParent($rm->getCurrentGroupId());
				return $this->redirect($this->generateUrl('BNSAppGroupBundle_group_list',array('type' => $groupType->getType())));
			}
		}
		return array(
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
			'groupType' => $groupType,
			'groups'	=> $cgm->getSubgroups(true),
			'form' => $form->createView()
		);
	}
	
	/**
	 * @Route("/fiche/{type}/{groupSlug}", name="BNSAppGroupBundle_group_sheet")
	 * @Template()
	 */
	public function groupSheetAction($type,$groupSlug)
	{
		$gm = $this->get('bns.group_manager');
		$rm = $this->get('bns.right_manager');
		$groupType = GroupTypeQuery::create()->joinWithI18n($rm->getLocale())->findOneByType(strtoupper($type));
		$group = GroupQuery::create()->findOneBySlug($groupSlug);
		$gm->setGroup($group);
		$rm->forbidIf($gm->getParent()->getId() != $rm->getCurrentGroupId());
		$rm->forbidIf(!$rm->hasRight(strtoupper($group->getGroupType()->getType()) . '_CREATE'));
		
		return array(
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
			'groups'	=> $gm->getSubgroups(true),
			'group' => $group,
			'groupType' => $groupType,
		);
		
	}
	
	
}
