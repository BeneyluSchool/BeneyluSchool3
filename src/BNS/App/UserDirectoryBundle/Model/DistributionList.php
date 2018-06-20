<?php

namespace BNS\App\UserDirectoryBundle\Model;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\UserDirectoryBundle\Model\om\BaseDistributionList;

class DistributionList extends BaseDistributionList
{

    public function isTypeStructures()
    {
        return DistributionListPeer::TYPE_STRUCT === $this->getType();
    }

    /**
     * Gets the related group ids
     *
     * @return array
     */
    public function getGroupIds()
    {
        return array_keys($this->getGroups());
    }

    /**
     * Gets the related groups
     *
     * @return array|Group[]
     */
    public function getGroups()
    {
        $groups = [];
        foreach ($this->getDistributionListGroupsJoinGroup() as $listGroup) {
            if (isset($groups[$listGroup->getGroupId()])) {
                continue;
            }
            $groups[$listGroup->getGroupId()] = $listGroup->getGroup();
        }

        return $groups;
    }

    /**
     * Gets the related role types ('TEACHER', 'DIRECTOR', ...)
     *
     * @return array
     */
    public function getRoleTypes()
    {
        if ($this->isTypeStructures()) {
            return null;
        }

        $names = [];
        foreach ($this->getRoles() as $role) {
            $names[] = $role->getType();
        }

        return $names;
    }

    /**
     * Gets the related roles
     *
     * @return array|GroupType[]
     */
    public function getRoles()
    {
        $roles = [];
        /** @var DistributionListGroup $listGroup */
        foreach ($this->getDistributionListGroupsJoinGroupType() as $listGroup) {
            if (isset($roles[$listGroup->getRoleId()])) {
                continue;
            }

            $roles[$listGroup->getRoleId()] = $listGroup->getGroupType();
        }

        return $roles;
    }

}
