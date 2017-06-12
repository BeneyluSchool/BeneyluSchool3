<?php

namespace BNS\App\GoogleBundle\Analytics;

/**
 * Where to find the report ID in the new Google Analytics ?
 * @see http://www.farstate.com/2011/12/where-to-find-the-report-id-in-the-new-google-analytics/
 * 
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class GoogleAnalyticsManager
{
	/**
	 * @var \gapi 
	 */
	private $api;
	
	/**
	 * @var int
	 */
	private $reportId;
	
	/**
	 * @param string $email
	 * @param string $password
	 */
	public function __construct($email, $password, $reportId)
	{
		$this->reportId = $reportId;
		
		include(__DIR__ . '/Api/gapi.class.php');
		$this->api		= new \gapi($email, $password);
	}
	
	/**
	 * * @param array  $dimensions Google Analytics dimensions e.g. array('browser')
	 * @param array  $metrics Google Analytics metrics e.g. array('pageviews')
	 * @param array  $sort_metric OPTIONAL: Dimension or dimensions to sort by e.g.('-visits')
	 * @param string $filter OPTIONAL: Filter logic for filtering results
	 * @param string $start_date OPTIONAL: Start of reporting period
	 * @param string $end_date OPTIONAL: End of reporting period
	 * @param int	 $start_index OPTIONAL: Start index of results
	 * @param int	 $max_results OPTIONAL: Max results returned
	 * 
	 * @return
	 */
	public function requestReportData($dimensions, $metrics, $sort_metric = null, $filter = null, $start_date = null, $end_date = null, $start_index = 1, $max_results = 30)
	{
		return $this->requestCustomReportData($this->reportId, $dimensions, $metrics, $sort_metric, $filter, $start_date, $end_date, $start_index, $max_results);
	}
	
	/**
	 * @param int	 $report_id
	 * @param array  $dimensions Google Analytics dimensions e.g. array('browser')
	 * @param array  $metrics Google Analytics metrics e.g. array('pageviews')
	 * @param array  $sort_metric OPTIONAL: Dimension or dimensions to sort by e.g.('-visits')
	 * @param string $filter OPTIONAL: Filter logic for filtering results
	 * @param string $start_date OPTIONAL: Start of reporting period
	 * @param string $end_date OPTIONAL: End of reporting period
	 * @param int	 $start_index OPTIONAL: Start index of results
	 * @param int	 $max_results OPTIONAL: Max results returned
	 * 
	 * @return
	 */
	public function requestCustomReportData($report_id, $dimensions, $metrics, $sort_metric = null, $filter = null, $start_date = null, $end_date = null, $start_index = 1, $max_results = 30)
	{
		return $this->api->requestReportData($report_id, $dimensions, $metrics, $sort_metric, $filter, $start_date, $end_date, $start_index, $max_results);
	}
	
	/**
	 * @return array
	 */
	public function getResults()
	{
		return $this->api->getResults();
	}
	
	/**
	 * @return \gapi
	 */
	public function getApi()
	{
		return $this->api;
	}
}