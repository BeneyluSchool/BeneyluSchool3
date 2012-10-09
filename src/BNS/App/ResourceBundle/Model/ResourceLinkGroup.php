<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLinkGroup;


/**
 * Skeleton subclass for representing a row from the 'resource_link_group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLinkGroup extends BaseResourceLinkGroup {

	const STATUS_ACTIVE = 1;
	const STATUS_DELETED = 0;
	
	public function deleteFromResource(){
		switch($this->getStatus()){
			case self::STATUS_ACTIVE: 
				$this->setStatus(self::STATUS_DELETED);
				$this->save();
			break;
				case self::STATUS_DELETED: 
				$this->delete();
			break;
		}
	}
	
} // ResourceLinkGroup
