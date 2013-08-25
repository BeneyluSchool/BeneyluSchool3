<?php

namespace BNS\App\GPSBundle\Model;

use BNS\App\GPSBundle\Model\om\BaseGpsPlace;
use Vich\GeographicalBundle\Annotation as Vich;


/**
 * Skeleton subclass for representing a row from the 'gps_place' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.GPSBundle.Model
 */

//ATTENTION ANNOTATION DESACTIVER LE TEMPS DE LA MIGRATION

/**
 * @Vich\Geographical(on="update")
 */
class GpsPlace extends BaseGpsPlace {
	
	
	public function setLatitude($value)
    {
        parent::setLatitude(str_replace(',','.',$value));
    }

    /**
     * Notice the longitude property must have a setter
     */
    public function setLongitude($value)
    {
        parent::setLongitude(str_replace(',','.',$value));
    }
	
	/**
     * @Vich\GeographicalQuery
     *
     * This method builds the full address to query for coordinates.
     */
	public function getAddress()
	{
		return parent::getAddress();
	}
	
	public function isActive()
	{
		return $this->getIsActive();
	}
	
	public function toggleActivation()
	{
		$this->setIsActive(!$this->isActive());
		$this->save();
	}
	
	public function getGroupId(){
		return $this->getGpsCategory()->getGroupId();
	}
	
	public function move($category){
		$this->setGpsCategoryId($category->getId());
		$this->save();
	}

} // GpsPlace
