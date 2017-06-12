<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class BackStatsController extends Controller
{
	/**
	 * @Route("/statistiques", name="BNSAppGroupBundle_back_stats")
	 * @Route("/export", name="group_manager_stats_classroom_export", defaults={"isExport":true})
	 * @Rights("GROUP_STAT_ACCESS")
	 */
	public function statsAction($isExport = false)
	{
        //test type requete
        $request = $this->getRequest();

        /*
        if($isExport) {
            $ret = $this->redirect($this->generateUrl("BNSAppStatisticsBundle_display_stats", array(
                'is_export'			=> true,
                'request'           => $request,
                'origin_path'       => 'group_manager_stats_classroom_export'
            )));
        }
        */
        
        return $this->render('BNSAppGroupBundle:Stats:index.html.twig', array(
			'is_export'			=> false,
            'request'           => $request,
            'origin_path'       => 'group_manager_stats_classroom_export'
		));
	}
}