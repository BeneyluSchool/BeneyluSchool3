<?php

namespace BNS\App\UserDirectoryBundle\Manager;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionList;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroup;
use BNS\App\UserDirectoryBundle\Model\DistributionListPeer;

/**
 * Class DistributionListManager
 *
 * @package BNS\App\UserDirectoryBundle\Manager
 */
class DistributionListManager
{

    public function create($groupId, array $targetGroupIds, array $groupTypes)
    {
        $list = new DistributionList();
        $list->setGroupId($groupId);

        $roles = GroupTypeQuery::create()
            ->filterByRole()
            ->filterByType($groupTypes)
            ->find()
        ;

        foreach ($targetGroupIds as $targetGroupId) {
            foreach ($roles as $role) {
                $listGroup = new DistributionListGroup();
                $listGroup->setGroupId($targetGroupId)
                    ->setGroupType($role)
                ;
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
        if (!(count($targetGroupIds) && count($groupTypes))) {
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
            // erase all old groups
            $list->getDistributionListGroups(null, $con)->delete($con);

            // create new ones
            foreach ($targetGroupIds as $targetGroupId) {
                foreach ($roles as $role) {
                    $listGroup = new DistributionListGroup();
                    $listGroup->setGroupId($targetGroupId)
                        ->setGroupType($role)
                    ;
                    $list->addDistributionListGroup($listGroup);
                }
            }
            $list->save($con);
        } catch (\Exception $e) {
            $con->rollBack();
            throw $e;
        }
        $con->commit();
    }

}
