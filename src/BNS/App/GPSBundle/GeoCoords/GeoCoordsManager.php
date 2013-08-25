<?php

namespace BNS\App\GPSBundle\GeoCoords;

/**
 * LocationMap.
 */
class GeoCoordsManager
{
	
	public $queryService;
	protected $object;
	
    public function __construct($queryService)
    {
        $this->queryService = $queryService;
    }
	
	public function getObject()
	{
		return $this->object;
	}
	
	public function setObject($object)
	{
		$this->object = $object;
	}
	
	public function setGeoCoords($object = null)
	{
		if($object != null)
			$this->setObject($object);
		
		if(!$this->getObject())
			throw new Exception("Please provide an Object to set GeoCoords");
		
		$query = $object->getAddress();
		
		$geoCoords = $this->queryService->queryCoordinates($query);
		
		$object->setLatitude($geoCoords->getLatitude());
		$object->setLongitude($geoCoords->getLongitude());	
	}	
}