<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends Controller
{

    /**
     * @Route("/", name="BNSAppGroupBundle_front")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $user = $this->getUser();
        $gm = $this->get('bns.right_manager')->getCurrentGroupManager();
        $group = $gm->getGroup();
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


        $this->get('bns.group_manager')->setGroup($group);

        $graphicChart = $this->container->hasParameter('graphic_chart') ? $this->container->getParameter('graphic_chart') : false;

        return array(
            "group_name" => $group->getLabel(),
            "group_home_message" => $gm->getAttribute('HOME_MESSAGE'),
            'noClassroomForTeacher' => $noClassroom,
            'noClassroomCookieKey' => $cookieKey,
            'userDirectoryManager' => $this->get('bns.user_directory.manager'),
            'mySchool' => $currentClassrooms,
            'group' => $group,
            'graphicChart' => $graphicChart
        );
    }

	/**
	 * @Route("/assign-classroom/{id}", name="BNSAppGroupBundle_assign_classroom")
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
			}
		}
		return $this->redirect($this->generateUrl('BNSAppGroupBundle_front'));
	}
}
