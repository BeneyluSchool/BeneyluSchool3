<?php

namespace BNS\App\GroupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends Controller
{

    /**
	 * @Route("/", name="BNSAppGroupBundle_front")
	 * @Template()
	 */
	public function indexAction()
    {
		$gm = $this->get('bns.right_manager')->getCurrentGroupManager();
		$group = $gm->getGroup();
        $classroomYears = array();
        $currentClassrooms = array();
        if ('SCHOOL' === $group->getType()) {
            $classrooms = $this->get('bns.user_manager')->getGroupsUserBelong('CLASSROOM');
            if ( 0 != $classrooms->count()) {
                foreach ($classrooms as $classroom) {
                    $classroomYears[] = $this->get('bns.group_manager')->setGroup($classroom)->getAttribute('CURRENT_YEAR');
                }
            }
            $mySchool = $this->get('bns.group_manager')->setGroup($group)->getSubgroupsByGroupType('CLASSROOM', true);

            foreach($mySchool as $classroomInSchool)
            {
                if($this->get('bns.group_manager')->setGroup($classroomInSchool)->getAttribute('CURRENT_YEAR') == $this->container->getParameter('registration.current_year'))
                {
                    $currentClassrooms[] = $classroomInSchool;
                }
            }
        }



		$this->get('bns.group_manager')->setGroup($group);

        $graphicChart = $this->container->hasParameter('graphic_chart') ? $this->container->getParameter('graphic_chart') : false;

		return array(
			"group_name"		=> $group->getLabel(),
			"group_home_message"	=> $gm->getAttribute('HOME_MESSAGE'),
			'noClassroomForTeacher' => count($currentClassrooms) > 0 && ( $this->get('bns.user_manager')->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(),'TEACHER')
				&& (!in_array($this->container->getParameter('registration.current_year'), $classroomYears) )),
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
