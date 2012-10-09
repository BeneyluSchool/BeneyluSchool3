<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupTypeQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'group_type' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupTypeQuery extends BaseGroupTypeQuery {

	public function filterByRole()
	{
		return $this->filterBySimulateRole(true);
	}
	
	public function filterByNotRole()
	{
		return $this->filterBySimulateRole(false);
	}
        
} // GroupTypeQuery
