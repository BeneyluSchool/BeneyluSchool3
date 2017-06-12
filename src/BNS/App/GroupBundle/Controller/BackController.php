<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\AdminBundle\Form\Type\AddToGroupType;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use BNS\App\CoreBundle\Form\Type\GroupSimpleType;
use BNS\App\CoreBundle\Form\Type\RuleType;
use BNS\App\CoreBundle\Form\Type\UserType;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\GroupBundle\Form\Model\EditGroupFormModel;
use BNS\App\GroupBundle\Form\Type\EditGroupType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;

use BNS\App\GroupBundle\Controller\CommonController;


/**
 * @Route("/gestion")
 */

class BackController extends CommonController
{

	/**
	 * @Template()
	 */
	public function sidebarAction($groupSlug = null, $page = null, $section = null)
	{
		$user = $this->get('bns.user_manager')->getUser();
		$gt = $this->get('bns.right_manager')->getManageableGroupTypes(false);
		$this->get('bns.user_manager')->setUser($user);
		$isEnv =  $this->get('bns.right_manager')->getCurrentGroupType() == 'ENVIRONMENT';
        $isSchool =  $this->get('bns.right_manager')->getCurrentGroupType() == 'SCHOOL';


		return array(
			'managementGroupTypes' => $gt,
			'isEnv'				   => $isEnv,
			'section'			   => $section,
			'page'				   => $page,
			'groupSlug'			   => $groupSlug,
            'hasCerise'            => $this->get('bns.right_manager')->hasCerise(),
            'isSchool'             => $isSchool,
            'hasMedialandes'       => $this->get('bns.right_manager')->hasMedialandes(),
		);
	}


	/**
	 * @Route("/search", name="BNSAppGroupBundle_back_search")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function indexAction()
    {
		$group = $this->get('bns.right_manager')->getCurrentGroup();
		$rm = $this->get('bns.right_manager');
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);
		//Formulaires de recherche

		$formUser = $this->createFormBuilder()
			->add('id','text',array('required' => false))
			->add('username','text',array('required' => false))
			->add('first_name','text',array('required' => false))
			->add('last_name','text',array('required' => false))
			->add('email','text',array('required' => false))
			->add('with_archived', 'checkbox',array('required' => false))
			->getForm();


		$gt = $this->get('bns.right_manager')->getManageableGroupTypes(false,'VIEW');

		$formAttr = array();

		foreach($gt as $item){
			$formAttr[$item->getType()] = $item->getLabel();
		}

		$formGroup = $this->createFormBuilder()
			->add('label','text',array('required' => false))
			->add('groupType','choice',array('required' => false,'empty_value' => $this->container->get('translator')->trans('CHOICE_ALL',array(),'GROUP'),'choices' => $formAttr))
			->getForm();
        $uaiTarget = null;


        if($rm->hasRight('USER_ASSIGNMENT'))
        {
            if($group->getGroupType()->getType() == "SCHOOL")
            {
                $uaiTarget = $gm->getAttribute('UAI');
            }
            $hasUai = $uaiTarget != null;
            $formAssignment = $this->createFormBuilder()
                ->add('uai','text',array('required' => true))
                ->add('uaiTarget',$hasUai ? 'hidden' : 'text',array('required' => false,'data' => $hasUai ? $uaiTarget : ""))
                ->getForm();
            $formAssignment = $formAssignment->createView();

        }else{
            $formAssignment = null;
        }

		return array(
			'group' => $group,
			'group_manager' => $gm,
			'formUser' => $formUser->createView(),
			'formGroup' => $formGroup->createView(),
            'formAssignment' => $formAssignment,
            'uaiTarget' => $uaiTarget
		);
    }

	/**
	 * @Route("/structure", name="BNSAppGroupBundle_back_structure")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function structureAction()
    {
		$group = $this->get('bns.right_manager')->getCurrentGroup();
		$rm = $this->get('bns.right_manager');
		$gm = $this->get('bns.group_manager');
		$gm->setGroup($group);

		return array(
			'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
			'group' => $group,
			'group_manager' => $gm,
		);
	}

	/**
	 * @Route("/mise-a-jour-parent", name="BNSAppGroupBundle_back_update_parent", options={"expose"=true} )
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function updateParentAction(){
		$parentId = $this->getRequest()->get('parent_id');
		$childId = $this->getRequest()->get('child_id');
		$gm = $this->get('bns.group_manager');
		$gm->findGroupById($childId);
		//TODO AME Sécu
		$gm->updateParents(array($parentId));
		return new Response();
	}


	/**
     * Bascule d'une règle
     *
	 * @param int $status Statut commandé à la centrale
	 *
     * @Route("/fiche/regles-bascule/{id}/{status}", name="BNSAppGroupBundle_group_rule_toggle")
     * @Template("BNSAppGroupBundle:Back:ruleToggle.html.twig")
	 * @Rights("GROUP_ACCESS_BACK")
     */
    public function toggleRuleAction($id,$status)
    {
		//TODO : secu
		$rule = $this->get('bns.rule_manager')->editRule(array('id' => $id,'state' => $status == '1' ? true : false));
		$gm = $this->get('bns.group_manager');
		$gm->findGroupById($this->getRequest()->getSession()->get('group_bundle_current_group_id'));
		$gm->clearGroupCache();
		return $this->redirect($this->generateUrl('BNSAppGroupBundle_group_sheet',array('groupSlug' => $gm->getGroup()->getSlug())));
	}

	/**
     * Suppression d'une règle
	 *
	 * @param int $status Statut commandé à la centrale
	 *
     * @Route("/fiche/regles-suppression/{id}/{groupSlug}", name="BNSAppGroupBundle_group_rule_delete")
	 * @Rights("GROUP_ACCESS_BACK")
     */
    public function deleteRuleAction($id, $groupSlug)
    {
		//TODO : secu
        $gm = $this->get('bns.group_manager');
        $group = $gm->findGroupBySlug($groupSlug);
        $gm->setGroup($group);
		$this->get('bns.rule_manager')->deleteRule($id);
		$gm->clearGroupCache();
		return $this->redirect($this->generateUrl('BNSAppGroupBundle_group_sheet',array('groupSlug' => $gm->getGroup()->getSlug())));
	}



	/**
                $this->get("stat.group")->newGroup(strtoupper($type));
	 * @Route("/personnalisation", name="BNSAppGroupBundle_back_custom")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function customAction(){
		$group = $this->get('bns.right_manager')->getCurrentGroup();
		$request = $this->getRequest();
		$form = $this->createForm(new EditGroupType(), new EditGroupFormModel($group));
		if ($request->isMethod('POST')) {
			$form->bind($request);
			//TODO checker validité form
			$form->getData()->save();
			return $this->redirect($this->generateUrl('BNSAppGroupBundle_back_custom'));
		}
		$homeMessage = $this->get('bns.right_manager')->getCurrentGroup()->getAttribute('HOME_MESSAGE');

		return array(
			'form' => $form->createView(),
			'homeMessage'     => $homeMessage,
		);

	}

    /**
     * Page d'accueil du module école en gestioon : tableau de bord + menu
     * @Route("/", name="BNSAppGroupBundle_back")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function moduleAction()
    {
        $activationRoles = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->orderByType(\Criteria::DESC)
            ->findByType(array('TEACHER', 'PUPIL', 'PARENT', 'DIRECTOR'))
        ;
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();

        return $this->render('BNSAppGroupBundle:Back:modules.html.twig', array(
            'activationRoles'      => $activationRoles,
            'uid'                  => $currentGroup->hasAttribute('UAI') ? $currentGroup->getAttribute('UAI') : '-'
        ));
    }
}
