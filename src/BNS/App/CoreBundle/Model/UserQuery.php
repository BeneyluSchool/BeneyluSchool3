<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseUserQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'user' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class UserQuery extends BaseUserQuery {

	public function childrenFilter($parentId){
		return $this->usePupilParentLinkRelatedByUserPupilIdQuery()->filterByUserParentId($parentId)->endUse();
	}
	
	public function parentsFilter($childId){
		return $this->usePupilParentLinkRelatedByUserParentIdQuery()->filterByUserPupilId($childId)->endUse();
	}
	
} // UserQuery
