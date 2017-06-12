<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Date\ExtendedDateTime;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion/statistiques")
 */
class BackAnalyticsStatsController extends Controller
{
	/**
	 * @Route("/visites", name="group_stats_visits")
	 * @Rights("GROUP_ACCESS_BACK")
	 */
    public function statsAction()
    {
		$dateMin = date('Y-m-d', (time() - 3600 * 24 * 30));
		$dateMax = date('Y-m-d', time());
		
		$analyticsManager = $this->get('google.analytics.manager');
		$analyticsManager->requestReportData(array('date'), array('visits'), array('date'), null, $dateMin, $dateMax);
		
		$visits = array();
		foreach ($analyticsManager->getResults() as $result) {
			$xCategories[] = strtotime($result->getDate());
			$visits[] = $result->getVisits();
		}
		
		return $this->render('BNSAppGroupBundle:Stats:visits.html.twig', array(
			'xCategories'	=> json_encode($xCategories),
			'data'			=> json_encode($visits),
			'startDate'		=> date('Y-m-d', time()) . ' 00:00:00'
		));
    }
	
	/**
	 * @Route("/pages-vues", name="group_stats_pages_views")
	 * @Rights("GROUP_ACCESS_BACK")
	 */
    public function pagesViewsAction()
    {
		$dateMin = date('Y-m-d', (time() - 3600 * 24 * 30));
		$dateMax = date('Y-m-d', time());
		
		$analyticsManager = $this->get('google.analytics.manager');
		$analyticsManager->requestReportData(array('date'), array('pageviews'), array('date'), null, $dateMin, $dateMax);
		
		$pagesViews = array();
		foreach ($analyticsManager->getResults() as $result) {
			$xCategories[] = strtotime($result->getDate());
			$pagesViews[] = $result->getPageViews();
		}
		
		return $this->render('BNSAppGroupBundle:Stats:pages_views.html.twig', array(
			'xCategories'	=> json_encode($xCategories),
			'data'			=> json_encode($pagesViews),
			'startDate'		=> date('Y-m-d', time()) . ' 00:00:00'
		));
    }
}