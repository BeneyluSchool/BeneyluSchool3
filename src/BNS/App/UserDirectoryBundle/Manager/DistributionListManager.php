<?php

namespace BNS\App\UserDirectoryBundle\Manager;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListPeer;

/**
 * Class DistributionListManager
 *
 * @package BNS\App\UserDirectoryBundle\Manager
 */
class DistributionListManager
{

    /**
     *
     *@var BNSGroupManager
     */
     protected $groupManager;


    /**
     * DistributionListManager constructor.
     */
    public function __construct(BNSGroupManager $groupManager)
    {
        $this->groupManager = $groupManager;
    }

    public function create($groupId, array $targetGroupIds, array $groupTypes)
    {
        $list = new DistributionList();
        $list->setGroupId($groupId);

        if (count($groupTypes)) {
            $roles = GroupTypeQuery::create()
                ->filterByRole()
                ->filterByType($groupTypes)
                ->find()
            ;
        } else {
            $list->setType(DistributionListPeer::TYPE_STRUCT);
            $roles = [];
        }

        foreach ($targetGroupIds as $targetGroupId) {
            if (count($roles)) {
                foreach ($roles as $role) {
                    $listGroup = new DistributionListGroup();
                    $listGroup->setGroupId($targetGroupId)
                        ->setGroupType($role)
                    ;
                    $list->addDistributionListGroup($listGroup);
                }
            } else {
                $listGroup = new DistributionListGroup();
                $listGroup->setGroupId($targetGroupId);
                $list->addDistributionListGroup($listGroup);
            }
        }

        return $list;
    }

    /**
     * Edits the given DistributionList
     *
     * @param DistributionList $list
     * @param array $targetGroupIds The related group ids. If not given, defaults to current group ids (no change).
     * @param array $groupTypes The related role names. If not given, defaults to current roles (no change).
     * @throws \Exception
     */
    public function edit(DistributionList $list, array $targetGroupIds = [], array $groupTypes = [])
    {
        if (!(count($targetGroupIds) && (count($groupTypes) || $list->isTypeStructures() ))) {
            return; // nothing will change, stop now
        }

        if (!count($targetGroupIds)) {
            $targetGroupIds = $list->getGroupIds();
        }

        if (count($groupTypes)) {
            $roles = GroupTypeQuery::create()
                ->filterByRole()
                ->filterByType($groupTypes)
                ->find()
            ;
        } else {
            $roles = $list->getRoles();
        }

        $con = \Propel::getConnection(DistributionListPeer::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $distributionGroups = new \PropelCollection();
            // create new ones
            foreach ($targetGroupIds as $targetGroupId) {
                if ($list->isTypeStructures()) {
                    $listGroup = new DistributionListGroup();
                    $listGroup->setGroupId($targetGroupId);
                    $distributionGroups[] = $listGroup;
                } else {
                    foreach ($roles as $role) {
                        $listGroup = new DistributionListGroup();
                        $listGroup->setGroupId($targetGroupId);
                        $listGroup->setRoleId($role->getId());
                        $distributionGroups[] = $listGroup;
                    }
                }
            }
            $list->setDistributionListGroups($distributionGroups, $con);
            $list->save($con);
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
        $con->commit();
    }


    public function getUserIds(DistributionList $distributionList) {
        $userIds = [];
        if ($distributionList->isTypeStructures()) {
            $groupIds = $distributionList->getGroupIds();
            foreach ($groupIds as $groupId) {
                $groupUserIds = $this->groupManager->setGroupById($groupId)->getUsersIds();
                $userIds = array_unique(array_merge($userIds, $groupUserIds));
            }
        } else {
            $distributionListGroups = $distributionList->getDistributionListGroups();
            foreach ($distributionListGroups as $distributionListGroup) {
                $groupUserIds = $this->groupManager->getUserIdsByRole($distributionListGroup->getRoleId(), $distributionListGroup->getGroupId());
                $userIds = array_unique(array_merge($userIds, $groupUserIds));
            }
        }
        return $userIds;
    }
}
