<?php

namespace BNS\App\AdminBundle\DataTables;

use Symfony\Component\HttpFoundation\Request;
use Criteria;

/**
 * @author Sylvain Lorinet
 */
class DataTables
{
	private $query;
	private $results;
	
	/**
	 * @param string	$modelName
	 * @param string	$name 
	 * @param array		$columnsName
	 * @param Request	$request 
	 */
	public function execute($baseQuery, Request $request, array $columnsName)
	{
		$query = clone $baseQuery;
		
		// Paging
		$query->offset($request->query->get('iDisplayStart'));
		$query->limit($request->query->get('iDisplayLength'));
		
		// Ordering
		if ($request->query->get('iSortingCols')) {
			for ($i=0; $i<$request->query->get('iSortingCols'); $i++) {
				if ($request->query->get('bSortable_' . intval($request->query->get('iSortCol_' . $i)))) {
					/* @var $query UserQuery */
					if ($request->query->get('sSortDir_' . $i) == 'asc') {
						$query->addAscendingOrderByColumn($columnsName[intval($request->query->get('iSortCol_' . $i))]);
					}
					else {
						$query->addDescendingOrderByColumn($columnsName[intval($request->query->get('iSortCol_' . $i))]);
					}
				}
			}
		}
		
		// Total row count
		$objectsCount = $baseQuery->count();

		// Filtering
		$totalDisplayRecords = $objectsCount;
		if (null != $request->query->get('sSearch')) {
			foreach ($columnsName as $key => $columnName) {
				if ($key == 0) {
					$query->add($columnName, '%' . $request->query->get('sSearch') . '%', Criteria::LIKE);
				}
				else {
					$query->addOr($columnName, '%' . $request->query->get('sSearch') . '%', Criteria::LIKE);
				}
			}

			$models = $query->find();

			$query->offset(0);
			$query->limit(0);
			$totalDisplayRecords = $query->count();
		}
		else
		{
			// Finaly
			$models = $query->find();
		}

		$this->results = $models;

		return array(
			'sEcho'					=> $request->query->get('sEcho'),
			'iTotalRecords'			=> $objectsCount,
			'iTotalDisplayRecords'	=> $totalDisplayRecords
		);
	}
	
	/**
	 * @return array 
	 */
	public function getResults()
	{
		return $this->results;
	}
	
	/**
	 * @return type 
	 */
	public function getQuery()
	{
		return $this->query;
	}
}