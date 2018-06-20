<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupPeer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends CommonController
{
    /**
     * Page d'accueil du module école : tableau uniquement
     * @Route("/", name="BNSAppSchoolBundle_front")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $user = $this->getUser();
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        $this->get('bns.right_manager')->forbidIf(
            $group->getGroupType()->getType() != 'SCHOOL' || !$this->get('bns.right_manager')->hasRight(null, $group->getId())
        );

        $noClassroom = false;
        $currentClassrooms = [];
        $cookieKey = 'noChooseClassroom_' . $user->getId() . '_' . $group->getId();
        if (!$request->cookies->get($cookieKey) && $this->get('bns.user_manager')->hasRoleInGroup($group->getId(), 'TEACHER')) {
            $currentYear = $this->container->getParameter('registration.current_year');
            $classroomYears = array();
            $classrooms = $this->get('bns.user_manager')->getGroupsUserBelong('CLASSROOM');
            foreach ($classrooms as $classroom) {
                $classroomYears[] = $classroom->getAttribute('CURRENT_YEAR');
            }

            if (0 === $classrooms->count()) {
                $noClassroom = true;
            } elseif ($group->getAafId() && $group->getAafAcademy() && !in_array($currentYear, $classroomYears)) {
                // aaf school
                $noClassroom = true;
            }

            if ($noClassroom) {
                $schoolClassrooms = $this->get('bns.group_manager')->setGroup($group)->getSubgroupsByGroupType('CLASSROOM', true);
                /** @var Group $classroomInSchool */
                foreach ($schoolClassrooms as $classroomInSchool) {
                    if ($classroomInSchool->getAttribute('CURRENT_YEAR') == $currentYear) {
                        $currentClassrooms[] = $classroomInSchool;
                    }
                }
            }

            $noClassroom = $noClassroom && count($currentClassrooms) > 0;
        }

        $lastFlux = array();
        $blackboard = null;
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        if ($this->get('bns.group_manager')->setGroup($group)->getProjectInfo('has_group_blackboard')) {
            $blackboard = $this->get('bns_core.blackboard_manager')->getBlackboard($group);
            if ($blackboard) {
                $lastFlux = $this->get('bns_core.blackboard_manager')->getLastNews($blackboard, $group);
            }
        }

        return array(
            'group' => $group,
            "group_name" => $group->getLabel(),
            "group_home_message" => $group->getAttribute('HOME_MESSAGE'),
            'noClassroomForTeacher' => $noClassroom,
            'noClassroomCookieKey' => $cookieKey,
            'userDirectoryManager' => $this->get('bns.user_directory.manager'),
            'mySchool' => $currentClassrooms,
            'blackboard' => $blackboard,
            'lastFlux' => $lastFlux
        );
    }

    /**
     * Page d'accueil du module école pour une école n'ayant pas encore été inscrite
     * @Route("/inactive", name="BNSAppSchoolBundle_front_inactive")
     * @Template()
     */
    public function inactiveAction()
    {
        $gm = $this->get('bns.right_manager')->getCurrentGroupManager();
        $schoolName = $gm->getParent()->getLabel();
        $schoolUai = $gm->getAttribute('UAI');
        $userName = $this->get('bns.right_manager')->getUserSession()->getLogin();
        return array(
            'schoolName' => $schoolName,
            'schoolUai'  => $gm->getParent()->getAttribute('UAI'),
            'login' => $this->get('bns.right_manager')->getUserSession()->getLogin()
        );
    }

    /**
     * @Route("/assign-classroom/{id}", name="BNSAppSchoolBundle_assign_classroom")
     */
    public function assignClassroomToUserAction($id = null)
    {
        if (null === $id) {
            throw new NotFoundHttpException();
        }

        $classroom = GroupQuery::create()
            ->useGroupTypeQuery()
                ->filterByType('CLASSROOM')
            ->endUse()
            ->findPk($id);

        if ($classroom) {
            $rightManager = $this->get('bns.right_manager');
            if (in_array($rightManager->getCurrentGroupId(), $this->get('bns.group_manager')->getParentIds($classroom->getId()))) {
                $user = $this->getUser();
                $classroom->setEnabled(true);
                $classroom->setValidationStatus(GroupPeer::VALIDATION_STATUS_VALIDATED);
                $classroom->save();
                $this->get('bns.classroom_manager')->setClassroom($classroom)->assignTeacher($user);
                $rightManager->setContext($classroom->getId());

                // identify user with his new classroom
                $this->get('bns.analytics.manager')->identifyUser($user, $classroom);

                // user is from AAF and has not done it previously: go to data recovery screen
                if ($user->getAafId()) {
                    return $this->redirect($this->generateUrl('account_link'));
                } else {
                    return $this->redirect($this->generateUrl('BNSAppClassroomBundle_front'));
                }
            }
        }
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_front'));
    }
}
