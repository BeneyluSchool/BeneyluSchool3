<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\AdminBundle\Form\Type\AddToGroupType;
use BNS\App\ClassroomBundle\Form\Type\NewUserInClassroomType;
use BNS\App\CoreBundle\Form\Type\GroupSimpleType;
use BNS\App\CoreBundle\Form\Type\RuleType;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\GroupBundle\Controller\CommonController;
use BNS\App\ClassroomBundle\Form\Type\ImportPupilFromCSVType;
use BNS\App\CoreBundle\Utils\StringUtil;

/**
 * @Route("/gestion/groupe")
 */
class BackGroupController extends CommonController
{

    /**
     * Fonction de récupération et de sécurisation du type de groupe
     * @param string $groupTypeType
     * @return GroupType $groupType
     * @throws NotFoundHttpException
     */
    protected function getGroupType($groupTypeType)
    {
        $groupType = GroupTypeQuery::create()
            ->findOneByType($groupTypeType);
        if (!$groupType) {
            throw new NotFoundHttpException("The group type with the type " . $groupTypeType . " has not been found.");
        }
        $this->canManageGroupType($groupType);
        return $groupType;
    }

    /**
     * Fonction de récupération et de sécurisation d'un groupe
     * @param string $groupSlug
     * @return Group $group
     * @throws NotFoundHttpException
     */
    protected function getGroup($groupSlug, $right = "VIEW")
    {
        $group = GroupQuery::create()
            ->findOneBySlug($groupSlug);
        if (!$group) {
            throw new NotFoundHttpException("The group with the slug " . $groupSlug . " has not been found.");
        }
        $this->canManageGroup($group, $right);
        return $group;
    }

    /**
     * Redirige vers la fiche d'un groupe
     * @param \BNS\App\CoreBundle\Model\Group $group
     * @return Redirection
     */
    protected function redirectSheet(Group $group, $edit = false)
    {
        if (!$edit) {
            $route = "BNSAppGroupBundle_group_sheet";
        } else {
            $route = "BNSAppGroupBundle_group_sheet_edit";
        }
        return $this->redirect(
            $this->generateUrl(
                $route,
                array(
                    'groupSlug' => $group->getSlug()
                )
            )
        );
    }

    /**
     * Liste les groupes par type
     * @Route("/liste-par-type/{groupTypeType}", name="BNSAppGroupBundle_group_list")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function listByTypeAction($groupTypeType)
    {
        $groupType = $this->getGroupType($groupTypeType);
        return array('groupType' => $groupType);
    }

    /**
     * Fonction appelée par le datatable pour récupérer dynamiquement les enregistrements
     * @param Request $request
     * @param string $groupTypeType
     * @Route("/liste-par-type-ajax/{groupTypeType}", name="BNSAppGroupBundle_group_list_by_type_ajax")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function listByTypeAjaxAction(Request $request, $groupTypeType = null)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }
        $dataTables = $this->get('datatables');
        $query = GroupQuery::create();
        $query->filterByArchived(false);
        if ($groupTypeType) {
            $groupType = $this->getGroupType($groupTypeType);
            $query
                ->useGroupTypeQuery()
                ->filterByType($groupType->getType())
                ->endUse();
        } else {
            $groupTypesIds = array();
            foreach ($this->get('bns.right_manager')->getManageableGroupTypes('VIEW') as $viewableGroupType) {
                $groupTypesIds[] = $viewableGroupType->getId();
            }
            $query
                ->useGroupTypeQuery()
                ->filterById($groupTypesIds)
                ->endUse();
        }
        $query->filterById($this->get('bns.right_manager')->getManageableGroupIds('VIEW'));

        if ($this->container->hasParameter('check_group_enabled') && $this->container->getParameter('check_group_enabled') == true && $groupTypeType) {
            if (in_array($groupTypeType, array('CLASSROOM', 'SCHOOL'))) {
                $query->filterByEnabled(true);
            }
        }

        $responses = $dataTables->execute($query, $request, array(
            GroupPeer::LABEL,
            GroupPeer::GROUP_TYPE_ID,
            GroupPeer::SLUG
        ));
        $results = $dataTables->getResults();
        foreach ($results as $key => $group) {
            //Distinction pour classes = on affiche le UAI de l'école
            if ($group->getGroupType()->getType() == "CLASSROOM" || $group->getGroupType()->getType() == "SCHOOL") {
                $cm = $this->get('bns.classroom_manager');
                $cm->setGroup($group);
                $responses['aaData'][$key][] = $group->getLabel() . ' - ' . $cm->getUai() . ' - ' . $cm->getAttribute('CITY');
            } else {
                $responses['aaData'][$key][] = $group->getLabel();
            }
            $link = '
                <a href="' . $this->generateUrl('BNSAppGroupBundle_group_sheet', array('groupSlug' => $group->getSlug())) . '" title=' . $this->get('translator')->trans('TITLE_SEE_CARD', array(), 'GROUP') . '>
                    <img src="/medias/images/icons/buttons/32x32/preview.png" />
                </a>';
            $responses['aaData'][$key][] = $link;
        }
        return new Response(json_encode($responses));
    }


    /**
     * Action de création d'un groupe, ayant le groupType en paramètre
     * @Route("/creer/{groupSlug}/{groupTypeType}", name="BNSAppGroupBundle_group_add")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addAction($groupSlug, $groupTypeType)
    {
        $groupType = $this->getGroupType($groupTypeType);
        $group = $this->getGroup($groupSlug, 'CREATE_CHILD');
        $newGroup = new Group();
        $form = $this->createForm(new GroupSimpleType(), $newGroup);
        if ($this->getRequest()->getMethod() == 'POST') {
            $form->bind($this->getRequest());
            if ($form->isValid()) {
                $gm = $this->get('bns.group_manager');
                $groupParams = array(
                    'type' => $groupType->getType(),
                    'group_type_id' => $groupType->getId(),
                    'label' => $newGroup->getLabel(),
                    'domain_id' => $this->container->getParameter('domain_id')
                );
                $newGroup = $gm->createGroup($groupParams);
                $groupManager = $this->get('bns.group_manager');
                $groupManager->setGroup($newGroup);
                $groupManager->updateParents(array($group->getId()));
                return $this->redirectSheet($newGroup, true);
            }
        }
        return array(
            'managementGroupTypes' => $this->get('bns.right_manager')->getManageableGroupTypes(),
            'groupType' => $groupType,
            'form' => $form->createView()
        );
    }

    /**
     * Action de recherche de groupes
     * @Route("/groupe/recherche", name="BNSAppGroupBundle_group_search")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function searchAction()
    {
        $gt = $this->get('bns.right_manager')->getManageableGroupTypes(false, 'VIEW');
        $formAttr = array();
        $gtId = array();
        $groups = null;

        foreach ($gt as $item) {
            $formAttr[$item->getType()] = $item->getLabel();
            $gtId[$item->getType()] = $item->getId();
        }
        //Création rapide d'un formulaire de recherche
        $form = $this->createFormBuilder()
            ->add('label', 'text', array('required' => false))
            ->add('groupType', 'choice', array('required' => false, 'empty_value' => $this->container->get('translator')->trans('CHOICE_ALL', array(), 'GROUP'), 'choices' => $formAttr))
            ->getForm();
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());
            //Si la donnée n'est pas settée, on la sort du filtre
            $datas = $form->getData();
            $groups = GroupQuery::create();
            if ($datas['label'] != null) {
                $groups->filterByLabel('%' . $datas['label'] . '%');
            }
            if ($datas['groupType'] != null) {
                $groups->filterByGroupTypeId($gtId[$datas['groupType']]);
            }
            $groups->filterById($this->get('bns.right_manager')->getManageableGroupIds('VIEW'));
            $groups = $groups->find();
        }
        return array('form' => $form->createView(), 'groups' => $groups);
    }

    /**
     * @Route("/supprimer/{groupSlug}", name="BNSAppGroupBundle_back_group_delete")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function deleteAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'DELETE');
        //Secu : pas de suppression d'envirronnement !
        $this->get('bns.right_manager')
            ->forbidIf($group->getGroupType()->getType() == 'ENVIRONMENT');
        $label = $group->getLabel();

        //Suppression définitive du groupe
        $this->get('bns.group_manager')->deleteGroup($group->getId());

        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('FLASH_GROUP_DELETE_SUCCESS', array('%label%' => $label), 'GROUP')
        );
        return $this->redirect($this->generateUrl('BNSAppGroupBundle_back', array()));
    }

    /**
     * @Route("/autoriser/{groupSlug}", name="BNSAppGroupBundle_back_group_autorise")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function autoriseAction($groupSlug)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->container->hasParameter('check_group_enabled') || !$this->container->getParameter('check_group_enabled') == true);
        $group = $this->getGroup($groupSlug, 'EDIT');
        $group->toggleEnabled();
        return $this->redirectSheet($group);
    }

    /**
     * Affiche la fiche d'un groupe
     * @Route("/fiche/{groupSlug}", name="BNSAppGroupBundle_group_sheet")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function sheetAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'VIEW');
        $gm = $this->get('bns.group_manager');
        $gm->setGroup($group);
        $viewableRoles = $this->get('bns.right_manager')->getManageableGroupTypes(true, 'VIEW');
        $viewableGroupTypes = $this->get('bns.right_manager')->getManageableGroupTypes(false, 'VIEW');
        $viewableGroupTypeArray = array();
        foreach ($viewableGroupTypes as $viewableRole) {
            $viewableGroupTypeArray[] = $viewableRole->getType();
        }
        $creatableRoles = $this->get('bns.right_manager')->getManageableGroupTypes(true, 'CREATE');
        $creatableGroupTypes = $this->get('bns.right_manager')->getManageableGroupTypes(false, 'CREATE');
        //Création du formulaire de règles
        $notAuthorisedRanks = $this->getNotAuthorisedRanks();
        $ruleForm = $this->createForm(new RuleType(array('not_authorised_ranks' => $notAuthorisedRanks)));
        $ruleForm->setData(array('rule_where_group_id' => $group->getId()));
        return array(
            'group' => $group,
            'viewableRoles' => $viewableRoles,
            'creatableGroupTypes' => $creatableGroupTypes,
            'creatableRoles' => $creatableRoles,
            'group_manager' => $this->get('bns.group_manager'),
            'rule_form' => $ruleForm->createView(),
            'not_authorised_ranks' => $notAuthorisedRanks,
            'parentGroups' => $gm->getParents(),
            'subGroups' => $gm->getSubgroups(true, false),
            'viewableGroupTypeArray' => $viewableGroupTypeArray
        );
    }

    /**
     * Fiche d'édition d'un groupe
     * @Route("/fiche-edition/{groupSlug}", name="BNSAppGroupBundle_group_sheet_edit")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function sheetEditAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'EDIT');
        $gm = $this->get('bns.group_manager');
        $rm = $this->get('bns.right_manager');
        $gm->setGroup($group);
        $parents = $gm->getParents();
        $availableGroupIds = $this->get('bns.right_manager')->getManageableGroupIds('CREATE_CHILD');
        //On clean les parents potentiels proposés
        foreach ($availableGroupIds as $groupId) {
            if ($groupId == $group->getId()) {
                unset($availableGroupIds[array_search($groupId, $availableGroupIds)]);
            }
            foreach ($parents as $parent) {
                if ($groupId == $parent->getId()) {
                    unset($availableGroupIds[array_search($groupId, $availableGroupIds)]);
                }
            }
        }

        $formView = null;
        if (sizeOf($availableGroupIds) > 0) {
            $formView = $this->createForm(new AddToGroupType($rm->getManageableGroupTypes(true, 'CREATE_CHILD'), $availableGroupIds))->createView();
        }
        $allAttributes = $group->getAttributes();
        $notAuthorisedAttributes = $this->getNotAuthorisedAttributes();

        $authorisedAttributes = array();
        foreach ($allAttributes as $attribute) {
            if (!in_array($attribute->getGroupTypeDataTemplateUniqueName(), $notAuthorisedAttributes)) {
                $authorisedAttributes[] = $attribute;
            }
        }
        return array(
            'group' => $group,
            'attributes' => $authorisedAttributes,
            'group_manager' => $gm,
            'parents' => $parents,
            'form' => $formView
        );
    }

    /**
     * Rendu des règles
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function rulesAction($groupSlug, $type = 'all')
    {
        $group = $this->getGroup($groupSlug, 'VIEW');
        $rm = $this->get('bns.right_manager');
        $gm = $this->get('bns.group_manager')->setGroup($group);
        $rules = $gm->getRules($type, false);
        $roles = array();
        //On affiche pas les règles interdites
        foreach (RankQuery::create()->filterByUniqueName($this->getNotAuthorisedRanks(), \Criteria::NOT_IN)->find() as $rank) {
            $ranks[$rank->getUniqueName()] = $rank->getLabel();
        }
        foreach ($this->get('bns.right_manager')->getManageableGroupTypes(true, 'GIVE_RIGHTS') as $role) {
            $roles[$role->getType()] = $role->getLabel();
        }
        return array(
            'gm' => $gm,
            'rules' => $rules,
            'ranks' => $ranks,
            'roles' => $roles,
            'type' => $type,
            'groupSlug' => $group->getSlug()
        );
    }

    /**
     * Ajout d'une règle
     * @Route("/ajout-regle/{groupSlug}", name="BNSAppGroupBundle_group_add_rule")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addRuleModalBodyAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'CREATE_RULE');
        $form = $this->createForm(
            new RuleType(null, array('not_authorised_ranks' => $this->getNotAuthorisedRanks()))
        );
        $form->setData(array('rule_where_group_id' => $group->getId()));
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());
            $params = $form->getData();
            if ($form->isValid() && $params['who_group_id'] != null && $params['rule_where_group_id'] != null && $params['rank_unique_name'] != null) {
                $ruleManager = $this->get('bns.rule_manager');
                //On manipule le tableau pour correspondre à l'API
                $ruleWho = array(
                    'domain_id' => $this->container->getParameter('domain_id'),
                    'group_parent_id' => $params['rule_where_group_id'],
                    'group_type_id' => $params['who_group_id']
                );
                $ruleWhere = array(
                    'group_id' => $params['rule_where_group_id'],
                    'belongs' => $params['rule_where_belongs']
                );
                if (trim($params['rule_where_group_type_id']) != "") {
                    $ruleWhere['group_type_id'] = $params['rule_where_group_type_id'];
                }
                $rule = array(
                    'state' => $params['state'],
                    'rank_unique_name' => $params['rank_unique_name'],
                    'who_group' => $ruleWho,
                    'rule_where' => $ruleWhere
                );
                $ruleManager->createRule($rule);
                return new Response('<script type="text/javascript">window.location.reload();</script>');
            } else {
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_ERROR_FILL_ALL_FIELD', array(), 'GROUP'));
            }
        }
        return
            array(
                'group' => $group,
                'form' => $form->createView()
            );
    }


    /**
     * Render d'une modal d'ajout d'un utilisateur
     * @Route("/generer-formulaire-ajout-utilisateur/{groupSlug}", name="BNSAppGroupBundle_group_add_user_modal_body", options={"expose"=true})
     * @Template("BNSAppGroupBundle:BackGroup:renderAddUserModalBody.html.twig")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addUserModalBodyAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug);
        $request = $this->getRequest();
        if (false === $request->isXmlHttpRequest()) {
            throw new HttpException(500, 'Must be XmlHttpRequest!');
        }
        $role = $request->get('user_role_requested', null);
        if ('POST' != $request->getMethod() || null == $role) {
            throw new HttpException(500, 'You must provide `user_role_requested` with `POST`\'s method!');
        }
        return
            array(
                'role' => $role,
                'form' => $this->createForm(new NewUserInClassroomType($role != 'PUPIL'))->createView(),
                'group' => $group
            );
    }

    /**
     *
     * @Route("/generer-formulaire-ajout-groupe/{groupSlug}", name="BNSAppGroupBundle_group_add_group_modal_body", options={"expose"=true})
     * @Template("BNSAppGroupBundle:BackGroup:renderAddGroupModalBody.html.twig")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addGroupModalBodyAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug);
        $request = $this->getRequest();
        if (false === $request->isXmlHttpRequest()) {
            throw new HttpException(500, 'Must be XmlHttpRequest!');
        }

        $groupType = $request->get('group_type_requested', null);
        if ('POST' != $request->getMethod() || null == $groupType) {
            throw new HttpException(500, 'You must provide `group_type_requested` with `POST`\'s method!');
        }

        return
            array(
                'groupType' => $groupType,
                'form' => $this->createForm(new GroupSimpleType(), new Group())->createView(),
                'group' => $group
            );
    }

    /**
     * Action d'ajout d'utilisateur
     * @Route("/ajouter-utilisateur/{groupSlug}", name="BNSAppGroupBundle_group_add_user", options={"expose"=true})
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addUserAction($groupSlug)
    {
        $request = $this->getRequest();
        if (false === $request->isXmlHttpRequest()) {
            throw new HttpException(500, 'Must be XmlHttpRequest!');
        }
        $group = $this->getGroup($groupSlug, 'VIEW');
        $role = $this->getGroupType($request->get('user_role', null), 'CREATE');

        $form = $this->createForm(new NewUserInClassroomType($role->getType() != 'PUPIL'));
        $form->bind($this->getRequest());
        if ($form['last_name']->getData() != null && $form['first_name'] != null && $form->isValid()) {
            $user = $form->getData();
            $user->save();

            $rom = $this->get('bns.role_manager');
            $rom->setGroupTypeRole($role)->assignRole($user->getObjectUser(), $group->getId());

            if ($this->getRequest()->get('user_role') == 'PUPIL') {
                $parent = $this->get('bns.user_manager')->createUser(
                    array(
                        'first_name' => $this->get('translator')->trans('FIRSTNAME_PARENT_OF', array(), 'GROUP'),
                        'last_name' => $user->getObjectUser()->getFullName(),
                        'lang' => $user->getObjectUser()->getLang(),
                        'username' => $user->getObjectUser()->getUsername() . 'PAR'
                    , false)
                );
                $roleParent = GroupTypeQuery::create()->findOneByType('PARENT');
                $rom->setGroupTypeRole($roleParent)->assignRole($parent, $group->getId());
                $this->get('bns.classroom_manager')->linkPupilWithParent($user->getObjectUser(), $parent);
            }

            return new Response(json_encode(true));
        }

        return new Response(json_encode(false));
    }

    /**
     * Action ajoutant un parent au groupe passé en paramètre
     * @param string $groupSlug Slug du parent groupe auquel on souhaite ajouter un parent
     * @Route("/ajouter-parent/{groupSlug}", name="BNSAppGroupBundle_group_add_parent")
     * @Template()
     */
    public function addParentAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'EDIT');
        $groupManager = $this->get('bns.group_manager');
        if ($this->getRequest()->getMethod() == 'POST') {
            $parent = $this->getRequest()->get('addToGroup');
            if ($parent['group_id'] != null) {
                //Check Secu
                $groupParent = GroupQuery::create()->findOneById($parent['group_id']);
                $groupParentChecked = $this->getGroup($groupParent->getSlug(), 'ADD_CHILD');
                $groupManager->addParent($group->getId(), $groupParentChecked->getId());
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PARENT_GROUP_ADD_SUCCESS', array(), 'GROUP'));
            }
        }
        return $this->redirectSheet($group, true);
    }

    /**
     * Action activant ou désactivant un espace de travail
     * @param string $groupSlug groupe auquel on enlève un parent
     * @Route("/activation-toggle/{groupSlug}", name="BNSAppGroupBundle_group_enable_toggle")
     */
    public function enableToggleAction($groupSlug)
    {
        //Check des groupes
        $group = $this->getGroup($groupSlug, 'VIEW');
        $this->get('bns.right_manager')->forbidIf(!$this->container->hasParameter('check_group_enabled') && $this->container->getParameter('check_group_enabled') != true);

        $group->toggleEnabled();

        $this->get('session')->getFlashBag()->add(
            'success',
            $group->isEnabled() ? $this->get('translator')->trans('FLASH_WORKING_SPACE_ALLOW', array(), 'GROUP') : $this->get('translator')->trans('FLASH_WORKING_SPACE_FORBIDDEN', array(), 'GROUP')
        );
        return $this->redirectSheet($group);
    }

    /**
     * Action supprimant un parent au groupe donné en paramètre
     * @param string $groupSlug groupe auquel on enlève un parent
     * @param string $parentSlug parent enlevé au groupe
     * @Route("/supprimer-parent/{groupSlug}/{parentSlug}", name="BNSAppGroupBundle_group_delete_parent")
     */
    public function deleteParentAction($groupSlug, $parentSlug)
    {
        //Check des groupes
        $group = $this->getGroup($groupSlug, 'DELETE');
        $groupParent = $this->getGroup($parentSlug, 'CREATE_CHILD');
        $gm = $this->get('bns.group_manager');
        $gm->deleteParent($group->getId(), $groupParent->getId());
        $this->get('session')->getFlashBag()->add(
            'success',
            $this->get('translator')->trans('FLASH_PARENT_GROUP_DELETE_SUCCESS', array(), 'GROUP'));
        return $this->redirectSheet($group, true);
    }

    /**
     * Edition des attributs d'un groupe
     * @Route("/parametre-formulaire/{groupSlug}", name="BNSAppGroupBundle_group_param_form" , options={"expose"=true})
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function paramFormAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'EDIT');
        $attributeUniqueName = $this->getRequest()->get('attribute_unique_name');
        $dataTemplate = GroupTypeDataTemplateQuery::create()->findOneByUniqueName($attributeUniqueName);
        $value = $this->getRequest()->get('value');

        if ($value != null) {
            $group->setAttribute($attributeUniqueName, $value);
            $render = 'value';
            if ($attributeUniqueName == 'NAME') {
                $gm = $this->get('bns.group_manager')->setGroup($group);
                $gm->updateGroup(array('label' => $value));
            }
        } else {
            $render = 'form';
            $value = $group->getAttribute($attributeUniqueName);
        }
        $type = $dataTemplate->getType();
        $collectionArray = array();
        switch ($type) {
            case "SINGLE":
            case "TEXT":
                $collection = null;
                break;
            case "ONE_CHOICE":
            case "MULTIPLE_CHOICE":
                $collection = GroupTypeDataChoiceQuery::create()->findByGroupTypeDataTemplateUniqueName($dataTemplate->getUniqueName());
                if (is_array($value)) {
                    foreach ($value as $val) {
                        $collectionArray[] = $val;
                    }
                } else {
                    $collectionArray[] = $value;
                }
                break;
        }
        return array(
            'value' => $value,
            'type' => $type,
            'collection' => $collection,
            'attributeUniqueName' => $attributeUniqueName,
            'group' => $group,
            'collectionArray' => $collectionArray,
            'render' => $render
        );
    }

    /**
     * Retourne la liste des utilisateur pour affichage en AJAX dans les Datatables
     * @Route("/liste-ajax/{roleType}/{groupSlug}", name="BNSAppGroupBundle_user_list_ajax")
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function listUserAjaxAction($roleType, $groupSlug)
    {
        $request = $this->getRequest();
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException();
        }
        $group = $this->getGroup($groupSlug, 'VIEW');
        $role = $this->getGroupType($roleType, 'VIEW');
        $gm = $this->get('bns.group_manager');
        $gm->setGroup($group);
        $searchParams = null;
        if ($request->get('iDisplayStart') && $request->get('iDisplayLength')) {
            $searchParams['offset'] = $request->get('iDisplayStart');
            $searchParams['limit'] = $request->get('iDisplayLength');
            $searchParams['order'] = 'asc';
        }
        if ($request->get('sSearch')) {
            $searchParams['query'] = $request->get('sSearch');
        }
        $users = $gm->getUsersByRoleUniqueName($role->getType(), true, $searchParams);
        $responses = array(
            'sEcho' => $this->getRequest('sEcho'),
            'iTotalRecords' => count($users),
            'iTotalDisplayRecords' => count($users)
        );

        $i = 0;

        if (count($users) > 0) {
            foreach ($users as $user) {
                $responses['aaData'][$i][] = $user->getFullName();
                if ($user->getLastConnection() == null) {
                    $responses['aaData'][$i][] = "---";
                } else {
                    $responses['aaData'][$i][] = $this->get('date_i18n')->process($user->getLastConnection(), 'medium', 'short');
                }
                $link = '
                <a href="' . $this->generateUrl('BNSAppGroupBundle_back_user_sheet', array('userId' => $user->getId())) . '" title=' . $this->get('translator')->trans('TITLE_SEE_CARD', array(), 'GROUP') . '>
                    <img src="/medias/images/icons/buttons/32x32/preview.png" />
                </a>';
                $responses['aaData'][$i][] = $link;

                $i++;
            }
        }
        if ($i == 0) {
            $responses['aaData'][$i][] = $this->get('translator')->trans('NO_USER_WITH_THIS_ROLE_IN_THIS_GROUP', array(), 'GROUP');
            $responses['aaData'][$i][] = "";
            $responses['aaData'][$i][] = "";
        }
        return new Response(json_encode($responses));
    }

    /**
     * @Route("/importer-eleve-depuis-ficher-csv/{groupSlug}", name="back_group_users_import_csv_pupil")
     * @Rights("GROUP_ACCESS_BACK")
     * @Rights("PUPIL_CREATE")
     */
    public function importPupilFromCSVIndexAction($groupSlug)
    {
        $group = $this->getGroup($groupSlug, 'VIEW');
        return $this->render('BNSAppGroupBundle:BackGroup:pupil_import.html.twig', array(
            'form' => $this->createForm(new ImportPupilFromCSVType())->createView(),
            'classroom_id' => $group->getId(),
            'group' => $group
        ));
    }

    /**
     * Action appelé lorsque l'utilisateur clique sur le bouton "J'ai terminé" de la page d'importation d'élèves grâce à un fichier CSV
     *
     * @Route("/importer-eleve", name="back_group_users_do_import_pupil_from_csv")
     * @Rights("GROUP_ACCESS_BACK")
     * @Rights("PUPIL_CREATE")
     */
    public function doImportPupilFromCSVAction()
    {
        $request = $this->getRequest();
        if (!$request->isMethod('POST')) {
            throw new HttpException(500, 'Request must be `POST`\'s method!');
        }

        $form = $this->createForm(new ImportPupilFromCSVType());
        $form->bind($request);
        if (null !== $form['file']->getData() && null !== $form['format']->getData() && $form->isValid() && $request->get('classroom_id') != null) {

            $classroom = GroupQuery::create()->findOneById(intval($request->get('classroom_id')));

            try {
                $result = $this->get('bns.classroom_manager')
                    ->setClassroom($classroom)
                    ->importPupilFromCSVFile($form['file']->getData(), $form['format']->getData());

                if ($result['success_insertion_count'] == $result['user_count']) {
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        $this->get('translator')->trans('FLASH_PROCESS_CSV_IMPORT', array('%userCount%' => $result['user_count']), "GROUP"));
                } else {
                    $this->get('session')->getFlashBag()->add(
                        'error',
                        $this->get('translator')->trans('FLASH_PROCESS_CSV_IMPORT_ERROR', array(
                            '%resultSuccess%' => $result['success_insertion_count'],
                            '%skiped%' => $result['skiped_count'],
                            '%failed%' => ($result['user_count'] - $result['success_insertion_count'] - $result['skiped_count']),
                        ), "GROUP"));
                    return $this->redirect($this->generateUrl('back_group_users_import_csv_pupil', array('groupSlug' => $classroom->getSlug())));
                }
            } catch (UploadException $e) {
                if ($e->getCode() == 1) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT', array(), "GROUP");
                } elseif ($e->getCode() == 2) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_PUPIL_FORMAT', array(), "GROUP");
                } elseif ($e->getCode() == 3) {
                    $msg = $this->get('translator')->trans('FLASH_CSV_INCORRECT_BENEYLU_FORMAT', array(), "GROUP");
                } else {
                    $msg = $this->get('translator')->trans('FLASH_ERROR_CONTACT_BENEYLU', array('%beneylu_brand_name%' => $this->container->getParameter('beneylu_brand_name')), "GROUP");
                }

                $this->get('session')->getFlashBag()->add('error', $msg);

                return $this->redirect($this->generateUrl('back_group_users_import_csv_pupil', array('groupSlug' => $classroom->getSlug())));
            }

            return $this->redirectSheet($classroom);
        }

        $this->get('session')->getFlashBag()->add('submit_import_form_error', '');

        return $this->render('BNSAppGroupBundle:BackGroup:pupil_import.html.twig', array('form' => $form->createView()));
    }

    /**
     * Action liant un utilisateur à un groupe depuis l'attachement rapide sur la fiche
     *
     * @Route("/lier-rapide", name="back_group_users_quick_linker")
     * @Rights("GROUP_ACCESS_BACK")
     * @Template()
     */
    public function quickLinkerAction()
    {
        $login = $this->getRequest()->get('login');
        $groupSlug = $this->getRequest()->get('groupSlug');
        $roleType = $this->getRequest()->get('roleType');
        $follow = $this->getRequest()->get('follow');
        $user = UserQuery::create()->findOneByLogin($login);
        $group = $this->getGroup($groupSlug, 'EDIT');
        $role = $this->getGroupType($roleType);
        $this->get('bns.role_manager')->setGroupTypeRoleFromType($roleType);
        $forPupil = $roleType == "PUPIL";


        if ($follow == 'true') {
            /**
             * Si la tick "follow" est tickée, on supprime l'ancienne affectation si le role n'était affecté qu'une seule fois à l'utilisateur ailleurs
             */
            $um = $this->get('bns.user_manager');
            $um->setUser($user);
            $oldRoles = $um->getGroupsAndRolesUserBelongs();
            $count = 0;
            $toDelete = array();
            foreach ($oldRoles as $key => $value) {
                //On ne considère pas le groupe ciblé
                if ($key != $group->getId()) {
                    if (isset($value['roles'])) {
                        foreach ($value['roles'] as $oldRoleType) {
                            $oldGroup = $value["group"];
                            if ($oldRoleType->getType() == $roleType) {
                                $count++;
                                $toDelete[] = $key;
                            }
                        }
                    }
                }
            }
            if (count($toDelete) > 0) {
                $gm = $this->get('bns.group_manager');
                foreach ($toDelete as $delete) {
                    $gm->setGroupById($delete);
                    $this->get('bns.role_manager')->setGroupTypeRoleFromType($roleType);
                    $this->get('bns.role_manager')->unassignRole($user->getId(), $delete, $roleType);
                    if ($forPupil) {
                        $parents = $user->getParents();
                        foreach ($parents as $parent) {
                            $this->get('bns.role_manager')->unassignRole($parent->getId(), $delete, 'PARENT');
                        }
                    }
                }
                $this->get('bns.role_manager')->setGroupTypeRoleFromType($roleType);
                $this->get('bns.role_manager')->assignRole($user, $group->getId());
                if ($forPupil) {
                    $parents = $user->getParents();
                    foreach ($parents as $parent) {
                        $this->get('bns.role_manager')->setGroupTypeRoleFromType('PARENT');
                        $this->get('bns.role_manager')->assignRole($parent, $group->getId());
                    }
                }
                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_USER_ADD_AND_DELETE_FROM_GROUP_SUCCESS', array(), 'GROUP'));
                return array('success' => true);
            } else {
                $this->get('bns.role_manager')->setGroupTypeRoleFromType($roleType);
                $this->get('bns.role_manager')->assignRole($user, $group->getId());
                if ($forPupil) {
                    $parents = $user->getParents();
                    foreach ($parents as $parent) {
                        $this->get('bns.role_manager')->setGroupTypeRoleFromType('PARENT');
                        $this->get('bns.role_manager')->assignRole($parent, $group->getId());
                    }
                }
                $this->get('session')->getFlashBag()->add('info', $this->get('translator')->trans('FLASH_USER_ADD_AND_DOESNT_DELETE_FROM_LAST_GROUP', array(), 'GROUP'));
                return array('success' => true);
            }
        } else {
            $this->get('bns.role_manager')->setGroupTypeRoleFromType($roleType);
            $this->get('bns.role_manager')->assignRole($user, $group->getId());
            if ($forPupil) {
                $parents = $user->getParents();
                foreach ($parents as $parent) {
                    $this->get('bns.role_manager')->setGroupTypeRoleFromType('PARENT');
                    $this->get('bns.role_manager')->assignRole($parent, $group->getId());
                }
            }
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_USER_ADD_SUCCESS', array(), 'GROUP'));
            return array('success' => true);
        }
        return array();
    }

    /**
     * Action affichant la page d'assignation des utilisateurs
     *
     * @Route("/affectation", name="BNSAppGroupBundle_group_user_assignment")
     * @Rights("SCHOOL_ASSIGNMENT")
     * @Template()
     */
    public function assignmentAction()
    {
        $gm = $this->get('bns.group_manager');
        $message = null;
        $uaiTarget = null;
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $gm->setGroup($group);
        if ($group->getGroupType()->getType() == "SCHOOL") {
            $uaiTarget = $gm->getAttribute('UAI');
        }
        $hasUai = $uaiTarget != null;
        $formAssignment = $this->createFormBuilder()
            ->add('uai', $hasUai ? 'hidden' : 'text', array('required' => true))
            ->add('uaiTarget', $hasUai ? 'hidden' : 'text', array('required' => false, 'data' => $hasUai ? $uaiTarget : ""))
            ->getForm();

        if ($this->getRequest()->isMethod('POST')) {
            $formAssignment->bind($this->getRequest());
            $datas = $formAssignment->getData();

            $uai = $datas['uai'];
            $uaiTarget = $datas['uaiTarget'];

            $school = GroupQuery::create()->filterBySingleAttribute('UAI', $uai)->findOne();
            if (!$school) {
                return array(
                    'error' => $this->get('translator')->trans('NO_SCHOOL_WITH_THIS_UAI', array(), 'GROUP'),
                    'formAssignment' => $formAssignment->createView()
                );
            }

            if ($uaiTarget) {
                $schoolTarget = GroupQuery::create()->filterBySingleAttribute('UAI', $uaiTarget)->findOne();
                if (!$schoolTarget) {
                    return array(
                        'error' => $this->get('translator')->trans('NO_SCHOOL_AIM_WITH_THIS_UAI', array(), 'GROUP'),
                        'formAssignment' => $formAssignment->createView()
                    );
                }
            } else {
                $schoolTarget = $school;
            }

            //Est on en affectation ?
            $teachers = $this->getRequest()->get('teachers');
            $pupils = $this->getRequest()->get('pupils');
            if (count($teachers) > 0 || count($pupils) > 0) {
                $envId = $this->get('bns.right_manager')->getCurrentEnvironment()->getId();
                $rom = $this->get('bns.role_manager');

                if ($this->getRequest()->get('assignmentType') == 'newClassroom') {
                    if ($this->getRequest()->get('newClassroomId') != null && $this->getRequest()->get('newClassroomId') != "false") {
                        $target = GroupQuery::create()->findOneById($this->getRequest()->get('newClassroomId'));
                        //TODO : check sécu
                    } else {

                        if ($this->getRequest()->get('newClassroomLabel') != null && $this->getRequest()->get('newClassroomLabel') != "") {
                            $classroomLabel = $this->getRequest()->get('newClassroomLabel');
                        } else {
                            $classroomLabel = $this->get('translator')->trans('NEW_CLASS', array(), 'GROUP');
                        }
                        $classroomLabel .= " - " . $this->container->getParameter('registration.current_year');
                        $target = $gm->createSubgroupForGroup(array('type' => "CLASSROOM", 'label' => $classroomLabel), $schoolTarget->getId());
                    }
                } elseif ($this->getRequest()->get('assignmentType') == 'link') {
                    $target = $schoolTarget;
                }

                //le GM est setté sur la classe
                if (count($teachers) > 0) {
                    $rom->setGroupTypeRoleFromType('TEACHER');
                    //On les déssassigne de l'ancienne classe
                    foreach ($teachers as $teacherId) {
                        $rom->unassignRole($teacherId, $envId, 'TEACHER');
                    }
                    $rom->setGroupTypeRoleFromType('TEACHER');
                    $rom->assignRoleForUsers(UserQuery::create()->findById($teachers), $target->getId());
                }
                if (count($pupils) > 0) {
                    $rom->setGroupTypeRoleFromType('PUPIL');
                    foreach ($pupils as $pupilId) {
                        $rom->unassignRole($pupilId, $envId, 'PUPIL');
                    }
                    $rom->setGroupTypeRoleFromType('PUPIL');
                    $rom->assignRoleForUsers(UserQuery::create()->findById($pupils), $target->getId());

                    //On fait pour leurs parents

                    $parents = UserQuery::create()
                        ->parentsFilter($pupils)
                        ->find();
                    $parentsIds = array();
                    foreach ($parents as $parent) {
                        $parentsIds[] = $parent->getId();
                    }

                    foreach ($parentsIds as $parentId) {
                        $rom->setGroupTypeRoleFromType('PARENT');
                        $rom->unassignRole($parentId, $envId, 'PARENT');
                    }

                    $rom->setGroupTypeRoleFromType('PARENT');
                    $rom->assignRoleForUsers(UserQuery::create()->findById($parentsIds), $target->getId());

                }

                $gm->setGroup($school);
                $gm->clearGroupCache();

                if ($school->getId() != $schoolTarget->getId()) {
                    $gm->setGroup($schoolTarget);
                    $gm->clearGroupCache();
                }

                if ($this->getRequest()->get('assignmentType') == 'newClassroom') {
                    $message = $this->get('translator')->trans('CREATE_NEW_CLASS_NUMBER_TEACHER_NUMBER_PUPIL', array(
                        '%teacherCount%' => count($teachers),
                        '%pupilCount%' => count($pupils)), 'GROUP');
                } elseif ($this->getRequest()->get('assignmentType') == 'link') {
                    $message = $this->get('translator')->trans('ALLOCATION_TO_SCHOOL_NUMBER_TEACHER_NUMBER_PUPIL', array(
                        '%schoolName%' => $schoolTarget->getLabel(),
                        '%teacherCount%' => count($teachers),
                        '%pupilCount%' => count($pupils)), 'GROUP');
                }
            }
        } else {
            return $this->redirect($this->generateUrl('BNSAppGroupBundle_back'));
        }

        $gm->setGroup($schoolTarget);
        $classroomTargets = $gm->getSubgroupsByGroupType('CLASSROOM', true);

        $gm->setGroup($school);
        $classrooms = $gm->getSubgroupsByGroupType('CLASSROOM', true);
        $teachers = $gm->getUsersByRoleUniqueName('TEACHER', true);

        //Calcul des élèves dans classe
        foreach ($classrooms as $classroom) {
            $gm->setGroup($classroom);
            foreach ($gm->getUsersByRoleUniqueNameIds('PUPIL') as $pupilId) {
                $pupilWithClassroomId[] = $pupilId;
            }
        }
        $gm->setGroup($school);
        $pupilWithClassroom = array();
        foreach ($gm->getUsersByRoleUniqueName('PUPIL', true) as $pupil) {
            if (!in_array($pupil->getId(), $pupilWithClassroomId)) {
                $pupilWithClassroom[] = $pupil;
            }
        }


        return array(
            'school' => $school,
            'schoolTarget' => $schoolTarget,
            'classrooms' => $classrooms,
            'classroomTargets' => $classroomTargets,
            'teachers' => $teachers,
            'gm' => $gm,
            'formAssignment' => $formAssignment->createView(),
            'message' => $message,
            'pupilWithClassroom' => $pupilWithClassroom
        );

    }

    /**
     * Action exportant en CSV la liste des utilisateurs pour une école
     *
     * @Route("/export-school/{groupSlug}", name="BNSAppGroupBundle_group_school_export")
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function schoolExportAction($groupSlug)
    {
        $school = $this->getGroup($groupSlug, 'VIEW');
        $this->get('bns.right_manager')->forbidIf($school->getGroupType()->getType() != "SCHOOL");
        //listage des utilisateurs
        $gm = $this->get('bns.group_manager')->setGroup($school);
        $classrooms = $gm->getSubgroups(true, false, GroupTypeQuery::create()->findOneByType('CLASSROOM')->getId());
        $rows = array();
        foreach ($classrooms as $classroom) {
            $gm = $this->get('bns.group_manager')->setGroup($classroom);
            $pupils = $gm->getUsersByRoleUniqueName('PUPIL', true);
            if ($pupils) {
                foreach ($pupils as $pupil) {
                    $rows [] = array(
                        str_replace(" ", "", str_replace("-", "", strtolower(StringUtil::filterString($pupil->getFirstName() . $pupil->getLastName())))),
                        $pupil->getLastName(),
                        $pupil->getFirstName(),
                        $classroom->getLabel(),
                        $pupil->getBirthday() ? $pupil->getBirthday()->format('Y-m-d') : ""
                    );
                }
            }
        }
        $handle = fopen('php://memory', 'r+');
        $header = array();

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, array(
            'Content-Type' => 'application/force-download',
            'Content-Disposition' => 'attachment; filename="export.csv"'
        ));
    }


    /**
     * @Route("/groupe/ajouter-recherche", name="BNSAppGroupBundle_group_addable_list" , options={"expose"=true})
     * @Template()
     * @Rights("GROUP_ACCESS_BACK")
     */
    public function addableListAction()
    {
        $term = $this->getRequest()->get('term');

        $gIds = $this->get('bns.right_manager')->getManageableGroupIds('VIEW');
        $gm = $this->get('bns.group_manager');

        $return = array();
        //Recherche UAI
        $uaiGroup = GroupQuery::create()
            ->filterByArchived(false)
            ->filterBySingleAttribute('UAI', $term)
            ->findOne();
        if ($uaiGroup && in_array($uaiGroup->getId(), $gIds)) {
            $return[] = array(
                'value' => $uaiGroup->getLabel() . ' - ' . $term,
                'label' => $uaiGroup->getLabel() . ' - ' . $term,
                'id' => $uaiGroup->getId()
            );
        }

        $termGroups = GroupQuery::create()
            ->filterByLabel('%' . $term . '%', \Criteria::LIKE)
            ->filterByArchived(false)
            ->filterById($gIds)
            ->limit(30)
            ->find();

        foreach ($termGroups as $termGroup) {
            $gm->setGroup($termGroup);
            $uai = $gm->getAttribute('UAI', "");
            if ($uai != "") {
                $uai .= ' - ';
            }

            $return[] = array(
                'value' => $termGroup->getLabel() . ' - ' . $termGroup->getGroupType()->getLabel(),
                'label' => $termGroup->getLabel() . ' - ' . $uai . $termGroup->getGroupType()->getLabel(),
                'id' => $termGroup->getId()
            );
        }

        if (count($termGroups) == 30) {
            $return[] = array(
                'value' => "",
                'label' => $this->get('translator')->trans('LIMIT_REACH_SPECIFY_SEARCH', array(), 'GROUP'),
                'id' => ""
            );
        }

        return new Response(json_encode($return));
    }
}
