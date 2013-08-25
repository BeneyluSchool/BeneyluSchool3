<?php

namespace BNS\App\GPSBundle\Map;

use Vich\GeographicalBundle\Map\Map as VichMap;

use BNS\App\GPSBundle\Model\GpsPlace;
use BNS\App\GPSBundle\Model\GpsPlaceQuery;
use BNS\App\GPSBundle\Model\GpsCategory;
use BNS\App\GPSBundle\Model\GpsCategoryQuery;

use Vich\GeographicalBundle\Map\Marker\MapMarker;

/**
 * LocationMap.
 */
class FrontMap extends VichMap
{
	
	protected $geoCoordsManager;
	protected $infoWindowBuilder;
	protected $iconGenerator;
	
    public function __construct($geoCoordsManager,$infoWindowBuilder,$iconGenerator,$groupManager)
    {
		parent::__construct();

		$this->geoCoordsManager = $geoCoordsManager;
		$this->infoWindowBuilder = $infoWindowBuilder;
		$this->iconGenerator = $iconGenerator;
		$this->groupManager = $groupManager;
		
		
        // configure your map in the constructor 
        // by setting the options
		
		$this->setZoom(8);
        $this->setContainerId('map_canvas');
        $this->setWidth(900);
        $this->setHeight(400);
		$this->setVarName('BNSMap');
		$this->setShowZoomControl(true);
		$this->setShowStreetViewControl(true);
		
		$this->setZoom(10);
    }
	
	public function initialize($group_id)
    {   		
		
		$this->groupManager->setGroupById($group_id);
		
		$groupGeocoords = $this->groupManager->getAttribute('GEOCOORDS');
		
		if(!$groupGeocoords){
			$fullAddress = $this->groupManager->getAttribute('ADDRESS') . " " . $this->groupManager->getAttribute('ZIPCODE') . " " . $this->groupManager->getAttribute('CITY');
			$coord = $this->geoCoordsManager->queryService->queryCoordinates($fullAddress);
			$groupGeocoords = $coord->getLatitude() . ';' . $coord->getLongitude();
			$this->groupManager->setAttribute('GEOCOORDS',str_replace(',','.',$groupGeocoords));
		}
	
		$has_geocoords = false;
		
		if($groupGeocoords){
			
			$grpLat = strstr($groupGeocoords,';',true);
			$grpLong = substr(strstr($groupGeocoords,';',false),1);
			//Si coordonnées différentes de 0;0
			if($grpLat != '0' && $grpLong != '0') {
				$marker = new MapMarker($grpLat,$grpLong);
				$marker->setVarname('marker_'.$group_id);
				$marker->setInfoWindow($this->infoWindowBuilder->build(array('obj' => $this->groupManager->getGroup(),'type' =>  'group')));
				$marker->getInfoWindow()->setVarName('info_window_' . $group_id);

				$iconUrl = $this->iconGenerator->generateIcon(new GpsPlace());
				if (null !== $iconUrl) {
					$marker->setIcon($iconUrl);
				}
				$this->addMarker($marker);
				
				$this->setCenter($grpLat,$grpLong);
				
				$has_geocoords = true;
			}
		}
		
		$categories = GpsCategoryQuery::create()
			->joinWith('GpsPlace')
			->where('GpsPlace.IsActive = ?',true)
			->filterByGroupId($group_id)
			->filterByIsActive(true)
			->orderByOrder('asc')
			->find();
		$type = 'GpsPlace';
		foreach($categories as $category){
			foreach($category->getGpsPlaces() as $place){
				if($place->getLatitude() == "" && $place->getLongitude() == ""){
					$this->geoCoordsManager->setGeoCoords($place);
				}
				$marker = new MapMarker($place->getLatitude(),$place->getLongitude());
				$marker->setVarname('marker_'.$place->getId());
				$marker->setInfoWindow($this->infoWindowBuilder->build(array('obj' => $place,'type' =>  'place')));
				$marker->getInfoWindow()->setVarName('info_window_' . $place->getId());
				
				$iconUrl = $this->iconGenerator->generateIcon($place);
				if (null !== $iconUrl) {
					$marker->setIcon($iconUrl);
				}
				$this->addMarker($marker);
				
				if( !$has_geocoords ) {
					$this->setCenter($place->getLatitude(),$place->getLongitude());
				}
				
			}
		}
		
		if(count($this->getMarkers()) == 1){
			//Si 1 marker => école
			
		}else{
			
		}
		$this->setShowInfoWindowsForMarkers(false);
		return array(
			'categories' => $categories,
			'group' => $this->groupManager->getGroup(),
			'has_geocoords' => $has_geocoords
		);
    }
	
}