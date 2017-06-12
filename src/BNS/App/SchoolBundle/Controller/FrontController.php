<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareuse\Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\ClassroomBundle\Model\GroupBlackboardQuery;
use Criteria;
use BNS\App\ClassroomBundle\Model\GroupBlackboard;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\HomeworkBundle\Model\HomeworkQuery;
use BNS\App\HomeworkBundle\Model\HomeworkGroupQuery;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
class FrontController extends CommonController
{
	/**
     * Page d'accueil du module école : tableau uniquement
     * @Route("/", name="BNSAppSchoolBundle_front")
	 * @Template()
     */
    public function indexAction()
    {
        $group = $this->get('bns.right_manager')->getCurrentGroupManager()->getGroup();
        $this->get('bns.right_manager')->forbidIf(
            $group->getGroupType()->getType() != 'SCHOOL' || !$this->get('bns.right_manager')->hasRight(null, $group->getId()));
        $classroomYears = array();
        $classrooms = $this->get('bns.user_manager')->getGroupsUserBelong('CLASSROOM');
        if ( 0 != $classrooms->count()) {
            foreach ($classrooms as $classroom) {
                $classroomYears[] = $this->get('bns.group_manager')->setGroup($classroom)->getAttribute('CURRENT_YEAR');
            }
        }

        $mySchool = $this->get('bns.group_manager')->setGroup($group)->getSubgroupsByGroupType('CLASSROOM', true);
        $currentClassrooms = array();
        foreach($mySchool as $classroomInSchool)
        {
            if($this->get('bns.group_manager')->setGroup($classroomInSchool)->getAttribute('CURRENT_YEAR') == $this->container->getParameter('registration.current_year'))
            {
                $currentClassrooms[] = $classroomInSchool;
            }
        }
        $this->get('bns.group_manager')->setGroup($group);

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
            "group_name"		=> $group->getLabel(),
            "group_home_message"	=> $group->getAttribute('HOME_MESSAGE'),
            'noClassroomForTeacher' => ($this->get('bns.user_manager')->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(),'TEACHER') && (!in_array($this->container->getParameter('registration.current_year'), $classroomYears) ) && (null !== $this->get('bns.user_manager')->getGroupsUserBelong('SCHOOL'))),
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

        if (0 != GroupQuery::create()->filterById($id)->count()) {
            $classroom = GroupQuery::create()->filterById($id)->findOne();
            if (in_array($this->get('bns.right_manager')->getCurrentGroup(), $this->get('bns.classroom_manager')->setClassroom($classroom)->getParents())) {
                $user = $this->get('bns.user_manager')->getUser();
                $this->get('bns.classroom_manager')->setClassroom($classroom)->assignTeacher($user);
                $this->get('bns.right_manager')->setContext($classroom->getId());

                // user is from AAF and has not done it previously: go to data recovery screen
                if (!$user->getAafLinked() && $user->getAafId()) {
                    return $this->redirect($this->generateUrl('account_link'));
                } else {
                    return $this->redirect($this->generateUrl('BNSAppClassroomBundle_front'));
                }
            }
        }
        return $this->redirect($this->generateUrl('BNSAppSchoolBundle_front'));
    }
}
