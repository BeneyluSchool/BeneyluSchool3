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
class GroupQuery extends BaseGroupQuery
{
    public function filterByGroupTypeName($type)
    {
        $this->useGroupTypeQuery()->filterByType($type, \Criteria::EQUAL)->endUse();

        return $this;
    }


	public function filterBySingleAttribute($attributeUniqueName,$attributeValue)
	{
		return $this
			->joinWith('GroupType')
			->joinWith('GroupType.GroupTypeData gtd')
			->joinWith('gtd.GroupTypeDataTemplate gtdt')
			->add('gtdt.unique_name', $attributeUniqueName)
			->joinWith('GroupData gd')
            // Force join to filter data by the good type
            ->addJoinCondition('gd', 'gd.GroupTypeDataId = gtd.Id')
			->add('gd.value', $attributeValue);
	}


    /**
     * Filter group query to allow only valid group (not archived, not with refused status
     * it also filter group based on two options $validatedGroup and $enabledGroup
     *
     * @param bool|false $enabledGroup use by Montpellier version equals
     * @return $this
     */
    public function filterByEnabledOnlyForStatistics($enabledGroup = false)
    {
        $groupTypeIds = GroupTypeQuery::create()
            ->filterByType(array('CLASSROOM', 'SCHOOL'))
            ->select('Id')
            ->find()
        ;

        $this
            ->filterByArchived(false)
            ->_if($enabledGroup)
                ->filterByEnabled(true, \Criteria::EQUAL)
                ->_or()
                ->filterByGroupTypeId($groupTypeIds, \Criteria::NOT_IN)
            ->_endif()
            ->filterByValidationStatus(GroupPeer::VALIDATION_STATUS_REFUSED, \Criteria::NOT_EQUAL)
        ;

        return $this;
    }

} // GroupQuery
