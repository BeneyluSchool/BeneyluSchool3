<?php

namespace BNS\App\ClassroomBundle\Controller;

use BNS\App\StatisticsBundle\Form\Model\StatsFilterFormModel;
use BNS\App\StatisticsBundle\Form\Type\StatsFilterType;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Date\ExtendedDateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BackStatsController extends Controller
{
	/**
	 * @Route("/", name="classroom_manager_stats")
	 * @Route("/export", name="classroom_manager_stats_classroom_export", defaults={"isExport":true})
	 *
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 */
	public function statsAction($isExport = false)
	{
        //test type requete
        $request = $this->getRequest();
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        return $this->render('BNSAppClassroomBundle:BackStats:index.html.twig', array(
			'is_export'			=> $isExport,
            'request'           => $request,
            'origin_path'       => 'classroom_manager_stats_classroom_export',
            'hasGroupBoard'     => $hasGroupBoard
		));
	}
}
