<?php

namespace BNS\App\GPSBundle\Model;

use BNS\App\GPSBundle\Model\om\BaseGpsCategory;


/**
 * Skeleton subclass for representing a row from the 'gps_category' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.GPSBundle.Model
 */
class GpsCategory extends BaseGpsCategory {

	public function isActive()
	{
		return $this->getIsActive();
	}
	
	public function toggleActivation()
	{
		$this->setIsActive(!$this->isActive());
		$this->save();
	}
	
} // GpsCategory
