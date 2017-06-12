<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\ClassroomBundle\Form\Type\PartnershipType;
use BNS\App\ClassroomBundle\Form\Model\PartnershipFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author El Mehdi Ouarour <el-mehdi.ouarour@atos.net>
 */
class BackPartnershipController extends Controller
{

    /**
     * @Route("/", name="BNSAppClassroomBundle_back_partnership", options={"expose"=true})
     * @Template()
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function indexAction()
    {
        $rightManager = $this->get('bns.right_manager');

        // Check des droits d'accès
        $rightManager->forbidIf(!$rightManager->isInClassroomGroup());

        $classroomManager = $this->get('bns.classroom_manager');
        $classroomManager->setClassroom($rightManager->getCurrentGroup());

        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        //Liste des partenariats auquels le groupe apartient
        $pm = $this->get('bns.partnership_manager');
        $partnerships = $pm->getPartnershipsGroupBelongs($rightManager->getCurrentGroup()->getId());

        $numberOfPartnershipsMembers = array();
        foreach ($partnerships as $partnership) {
            $numberOfPartnershipsMembers[$partnership->getId()] = $pm->getNumberOfPartnershipMembers($partnership->getId());
        }

        $canPartnershipWithHighSchool = false;
        $canPartnershipWithSchool = false;
        if ($this->get('bns.right_manager')->hasRight('CLASSROOM_CREATE_HIGH_SCHOOL_PARTNERSHIP')) {
            $classrooms = $this->getHighSchoolRelatedClassrooms();
            $currentSchool = $this->getCurrentSchool();
            if ('HIGH_SCHOOL' === $currentSchool->getType()) {
                $canPartnershipWithSchool = !!count($classrooms);
            } else {
                $canPartnershipWithHighSchool = !!count($classrooms);
            }
        }

        return array(
            'classroom' => $rightManager->getCurrentGroup(),
            'can_partnership_with_high_school' => $canPartnershipWithHighSchool,
            'can_partnership_with_school' => $canPartnershipWithSchool,
            'partnerships' => $partnerships,
            'numberOfPartnershipsMembers' => $numberOfPartnershipsMembers,
            'hasGroupBoard' => $hasGroupBoard
        );
    }

    /**
     * Ajout d'un nouveau partenariat
     * @Route("/nouveau-partenariat/{highSchool}", name="BNSAppClassroomBundle_back_add_new_partnership", requirements={"highSchool": "college"})
     * @Template("BNSAppClassroomBundle:BackPartnership:form.html.twig")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param bool $highSchool
     * @return array
     */
    public function addAction($highSchool = false)
    {
        $allClassrooms = [];
        if ($highSchool && $this->get('bns.right_manager')->hasRight('CLASSROOM_CREATE_HIGH_SCHOOL_PARTNERSHIP')) {
            $allClassrooms = $this->getHighSchoolRelatedClassrooms();
        }

        $form = $this->createForm(
            new PartnershipType(),
            new PartnershipFormModel($this->get('bns.right_manager')->getCurrentGroupId(), $this->get('bns.partnership_manager'), false),
            [
                'classrooms' => $allClassrooms,
            ]
        );

        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                /** @var Group $partnership */
                $partnership = $form->getData()->save();
                if ($highSchool) {
                    $partnership->setAttribute('IS_HIGH_SCHOOL', 1);
                }
                $centralGroup = $this->get('bns.group_manager')->getGroupFromCentral($partnership->getId());
                $this->get('session')->getFlashBag()->add('success',
                    $this->get('translator')->trans('FLASH_PARTNERSHIP_CREATE_SUCCESS_WITH_CODE', array('%code%' => $centralGroup['uid']), 'CLASSROOM')
                );

                return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_partnership'));
            }
        }
        return array(
            'high_school' => $highSchool,
            'current_school' => $this->getCurrentSchool(),
            'form' => $form->createView(),
            'isEditionMode' => false
        );
    }

    /**
     * Vérification de l'existance d'un partenariat
     * @Route("/verifier-identifiant-partenariat", name="classroom_manager_verify_partnership_id")
     * @Template("BNSAppClassroomBundle:BackClassroomModal:check_partnership_id_result_block.html.twig")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function verifyUidAction()
    {
        //check si Ajac, Post et des bon paramètres
        $partnershipUid = $this->checkAjaxRequests($this->getRequest());

        $pm = $this->get('bns.partnership_manager');
        $partnership = $pm->getPartnershipByUid($partnershipUid);

        $isAlreadyMember = false;

        if (null != $partnership) {
            $isAlreadyMember = $pm->isAlreadyMemberofPartnership($partnership->getId(), $this->get('bns.right_manager')->getCurrentGroup()->getId());
        }

        return array(
            'partnershipId' => $partnershipUid,
            'partnership' => $partnership,
            'is_already_member_of_partnership' => $isAlreadyMember
        );
    }

    /**
     * Joindre un partenariat
     * @Route("/rejoindre-partenariat", name="classroom_manager_join_partnership")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function joinAction()
    {
        //check si Ajac, Post et des bon paramètres
        $partnershipUid = $this->checkAjaxRequests($this->getRequest());

        $pm = $this->get('bns.partnership_manager');
        $response = $pm->joinPartnership($partnershipUid, $this->get('bns.right_manager')->getCurrentGroup()->getId());

        if (true === $response) {
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PARTNERSHIP_JOIN', array(), "CLASSROOM"));
        } else {
            $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('FLASH_PARTNERSHIP_ALREADY_IN', array(), "CLASSROOM"));
        }

        return new Response();
    }

    /**
     * Fiche d'un partenariat
     * @Route("/fiche-partenariat/{partnershipSlug}", name="BNSAppClassroomBundle_back_partnership_detail")
     * @Template()
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $partnershipSlug
     */
    public function detailsAction($partnershipSlug)
    {

        $pm = $this->get('bns.partnership_manager');
        $partnership = $pm->findGroupBySlug($partnershipSlug);

        //ckeck si le slug correspond à un groupe de type partenariat
        $pm->checkPartnershipExists($partnership);

        //check si le groupe courant est membre du partenariat
        $currentGroupId = $this->get('bns.right_manager')->getCurrentGroup()->getId();
        $pm->checkIfGroupMemberOfPartnership($partnership, $currentGroupId);

        //liste des modules du partenariats
        $activationRoles = GroupTypeQuery::create()->filterBySimulateRole(true)->orderByType(\Criteria::DESC)->findByType(array('TEACHER', 'PUPIL', 'PARENT'));


        //Membres du patenariat
        $members = $pm->getPartnershipMembers($partnership->getId());

        //Nom des parents ges groupes membres du partenariat
        $membersParentName = array();
        if (sizeof($members) > 1) {
            $membersParentName = $pm->getParentsNamesOfMembers($members);
        }

        $centralGroup = $pm->getGroupFromCentral($partnership->getId());

        return array(
            'activationRoles' => $activationRoles,
            'partnership' => $partnership,
            'partnershipUid' => $centralGroup['uid'],
            'members' => $members,
            'membersParentName' => $membersParentName,
            'currentGroupId' => $currentGroupId
        );
    }

    /**
     * Action pour quitter un partenariat un partenariat
     * Cette action est utilisé également pour supprimer un partenariat
     * @Route("/quitter-partenariat/{partnershipSlug}", name="BNSAppClassroomBundle_back_leave_partnership")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $partnershipSlug
     */
    public function leaveAction($partnershipSlug)
    {
        $pm = $this->get('bns.partnership_manager');
        $partnership = $pm->findGroupBySlug($partnershipSlug);

        //ckeck si le slug correspond à un groupe de type partenariat
        $pm->checkPartnershipExists($partnership);

        //check si le groupe courant est membre du partenariat
        $currentGroupId = $this->get('bns.right_manager')->getCurrentGroup()->getId();
        $pm->checkIfGroupMemberOfPartnership($partnership, $currentGroupId);

        $deleted = $pm->leavePartnership($partnership->getId(), $currentGroupId);

        if ($deleted)
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PARTNERSHIP_DELETED', array(), "CLASSROOM"));
        else
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PARTNERSHIP_LEAVE', array(), "CLASSROOM"));

        return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_partnership'));
    }

    /**
     * Fiche d'édition d'un partenariat
     * @Route("/fiche-partenariat/{partnershipSlug}/editer", name="BNSAppClassroomBundle_back_partnership_edit")
     * @Template("BNSAppClassroomBundle:BackPartnership:form.html.twig")
     * @Rights("CLASSROOM_ACCESS_BACK")
     *
     * @param String $partnershipSlug
     */
    public function editAction($partnershipSlug)
    {
        $pm = $this->get('bns.partnership_manager');
        $partnership = $pm->findGroupBySlug($partnershipSlug);

        //ckeck si le slug correspond à un groupe de type partenariat
        $pm->checkPartnershipExists($partnership);

        //check si le groupe courant est membre du partenariat
        $currentGroupId = $this->get('bns.right_manager')->getCurrentGroup()->getId();
        $pm->checkIfGroupMemberOfPartnership($partnership, $currentGroupId);

        $form = $this->createForm(new PartnershipType(), new PartnershipFormModel($this->get('bns.right_manager')->getCurrentGroupId(), $pm, true, $partnership));

        $request = $this->getRequest();

        if ('POST' === $request->getMethod()) {
            $form->bind($request);
            if ($form->isValid()) {
                $form->getData()->save();

                return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_partnership_detail', array(
                            'partnershipSlug' => $partnership->getSlug()
                )));
            }
        }

        $centralGroup = $pm->getGroupFromCentral($partnership->getId());

        return array(
            'partnership' => $partnership,
            'partnershipUid' => $centralGroup['uid'],
            'form' => $form->createView(),
            'isEditionMode' => true
        );
    }

    /**
     * Appellé depuis le contolleur pour checker la requete ajax
     *
     * @param type $request
     *
     * @return type
     *
     * @throws NotFoundHttpException
     * @throws InvalidArgumentException
     */
    public function checkAjaxRequests($request)
    {
        if (false === $request->isXmlHttpRequest() || 'POST' !== $request->getMethod()) {
            throw new NotFoundHttpException("This page except an AJAX & POST header");
        }

        $partnershipUid = $request->get('partnership_id', null);
        if (null == $partnershipUid) {
            throw new \InvalidArgumentException('The parameter "partnership_id" is missing !');
        }

        return $partnershipUid;
    }

    protected function getCurrentSchool()
    {
        $currentClassroom = $this->get('bns.right_manager')->getCurrentGroup();

        return $this->get('bns.group_manager')->setGroup($currentClassroom)->getParent();
    }

    protected function isCycle3Classroom(Group $classroom)
    {
        foreach (['CM1', 'CM2'] as $cycle3Level) {
            if (is_int(strpos($classroom->getLevelsString(), $cycle3Level))) {
                return true;
            }
        }

        return false;
    }

    protected function getHighSchoolRelatedClassrooms()
    {
        $allClassrooms = [];
        $currentSchool = $this->getCurrentSchool();
        $currentClassroom = $this->get('bns.right_manager')->getCurrentGroup();
        if ('HIGH_SCHOOL' === $currentSchool->getType()) {
            // find all schools related to the current high school, and find all cycle 3 classrooms in these schools
            $schools = GroupQuery::create()
                ->useGroupDataQuery()
                    ->filterByValue($currentSchool->getId())
                    ->useGroupTypeDataQuery()
                        ->filterByGroupTypeDataTemplateUniqueName('HIGH_SCHOOL_ID')
                    ->endUse()
                ->endUse()
                ->find();
            foreach ($schools as $school) {
                // setup school parent, for full label display
                $city = null;
                $parents = $this->get('bns.group_manager')->setGroup($school)->getParents();
                foreach ($parents as $parent) {
                    if ($parent->getType() === 'CITY') {
                        $school->parent = $parent;
                        break;
                    }
                }
                $cycle3Classrooms = [];
                $classrooms = $this->get('bns.group_manager')->setGroup($school)->getSubgroupsByGroupType('CLASSROOM');
                /** @var Group $classroom */
                foreach ($classrooms as $classroom) {
                    if ($this->isCycle3Classroom($classroom)) {
                        $classroom->parent = $school;
                        $classroom->icon = ['type' => 'CLASSROOM'];
                        $cycle3Classrooms[] = $classroom;
                    }
                }
                $allClassrooms = array_merge($allClassrooms, $cycle3Classrooms);
            }
            // add all other classrooms of the current high school
            $currentClassroom = $this->get('bns.right_manager')->getCurrentGroup();
            $classrooms = $this->get('bns.group_manager')->setGroup($currentSchool)->getSubgroupsByGroupType('CLASSROOM');
            /** @var Group $classroom */
            foreach ($classrooms as $classroom) {
                if ($classroom->getId() !== $currentClassroom->getId() && is_int(strpos($classroom->getLevelsString(), '6EME'))) {
                    $classroom->parent = $currentSchool;
                    $allClassrooms[] = $classroom;
                }
            }
        } else if ($this->isCycle3Classroom($currentClassroom)) {
            // find all cycle 3 classrooms in the related high school
            $school = GroupQuery::create()->findPk($currentSchool->getAttribute('HIGH_SCHOOL_ID'));
            if ($school) {
                $classrooms = $this->get('bns.group_manager')->setGroup($school)->getSubgroupsByGroupType('CLASSROOM');
                /** @var Group $classroom */
                foreach ($classrooms as $classroom) {
                    if (is_int(strpos($classroom->getLevelsString(), '6EME'))) {
                        $classroom->parent = $school;
                        $allClassrooms[] = $classroom;
                    }
                }
            }
        }

        return $allClassrooms;
    }

}
