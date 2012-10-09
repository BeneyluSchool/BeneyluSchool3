<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupQuery;

use BNS\App\CoreBundle\Model\GroupDataQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupQuery extends BaseGroupQuery {
	
	
	public function filterBySingleAttribute($attributeUniqueName,$attributeValue)
	{
		return $this
			->useGroupTypeQuery()
				->useGroupTypeDataQuery()
					->useGroupDataQuery()
						->filterByValue($attributeValue)
					->endUse()
					->useGroupTypeDataTemplateQuery()
						->filterByUniqueName($attributeUniqueName)
					->endUse()
				->endUse()
			->endUse();	
	}
	
} // GroupQuery
