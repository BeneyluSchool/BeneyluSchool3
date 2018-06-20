<?php

namespace BNS\App\HomeworkBundle\Homework;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\HomeworkBundle\Model\Homework;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class HomeworkRightManager
{
    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     *  cache groupIds where user has permission
     * @var array
     */
    protected $groupIdsByUserByPermission = [];

    /**
     *  cache pupils that as user can access (with permission)
     * @var array
     */
    protected $pupilIdsByUserByPermission = [];

    /**
     * HomeworkRightManager constructor.
     * @param BNSUserManager $userManager
     * @param BNSGroupManager $groupManager
     */
    public function __construct(BNSUserManager $userManager, BNSGroupManager $groupManager)
    {
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    /**
     * return linked groups that the user is allowed to see
     *
     * @param Homework $homework
     * @param User $user
     * @return array|Group[]
     */
    public function getHomeworkGroups(Homework $homework, User $user)
    {
        $groups = $homework->getGroups();

        return $this->filterGroups($groups, $user, [
            'HOMEWORK_ACCESS',
            'HOMEWORK_ACCESS_BACK',
        ]);
    }

    /**
     * return linked users that the user is allowed to see
     *
     * @param Homework $homework
     * @param User $user
     * @return array|User[]
     */
    public function getHomeworkUsers(Homework $homework, User $user)
    {
        $users = $homework->getUsers();
        if ($user->isChild()) {
            // Children only saw them if they are affected
            foreach ($users as $currentUser) {
                if ($user->getId() === $currentUser->getId()) {
                    return [$currentUser];
                }
            }

            return [];
        }

        return $this->filterUsers($users, $user, ['HOMEWORK_ACCESS_BACK']);
    }

    /**
     * return linked children of the user
     *
     * @param Homework $homework
     * @param User $user
     * @return array|User[]
     */
    public function getHomeworkChildren(Homework $homework, User $user)
    {
        if ($user->isChild()) {
            return [];
        }

        $children = $user->getActiveChildren();
        if (!count($children)) {
            return [];
        }
        $groupIds = $homework->getGroupsIds();
        $userIds = $homework->getUserIds();
        $allowedChildren = [];
        foreach ($children as $child) {
            if (in_array($child->getId(), $userIds)) {
                $allowedChildren[] = $child;

            } else {
                $childGroupIds = $this->getAllowedGroupIds($child, 'HOMEWORK_ACCESS');
                if (count(array_intersect($groupIds, $childGroupIds)) > 0) {
                    $allowedChildren[] = $child;
                }
            }
        }

        return $allowedChildren;
    }

    /**
     * @param User $user
     * @param $permission
     * @return int[]|array
     */
    public function getAllowedGroupIds(User $user, $permission)
    {
        if (!isset($this->groupIdsByUserByPermission[$user->getId()]) || !isset($this->groupIdsByUserByPermission[$user->getId()][$permission])) {
            $oldUser = $this->userManager->getUser();
            $this->userManager->setUser($user);
            $this->groupIdsByUserByPermission[$user->getId()][$permission] = $this->userManager->getGroupIdsWherePermission($permission);
            if ($oldUser) {
                $this->userManager->setUser($oldUser);
            }
        }

        return $this->groupIdsByUserByPermission[$user->getId()][$permission];
    }

    /**
     * @param User $user
     * @param string $permission
     * @return int[]|array
     */
    public function getAllowedPupilIds(User $user, $permission)
    {
        if (!isset($this->pupilIdsByUserByPermission[$user->getId()]) || !isset($this->pupilIdsByUserByPermission[$user->getId()][$permission])) {

            $groupIds = $this->getAllowedGroupIds($user, $permission);

            $pupilRoleId = (int)GroupTypeQuery::create()
                ->filterByType('PUPIL')
                ->filterBySimulateRole(true)
                ->select(['Id'])
                ->findOne()
            ;
            $allowedUserIds = [];
            $groups = GroupQuery::create()->filterById($groupIds)->joinGroupType()->find();
            foreach ($groups as $group) {
                if ($group->isPartnerShip()) {
                    $this->groupManager->setGroup($group);
                    foreach ($this->groupManager->getPartnersIds() as $partnerId) {
                        $allowedUserIds = array_merge($allowedUserIds, $this->groupManager->getUserIdsByRole($pupilRoleId, $partnerId));
                    }
                }else {
                    $allowedUserIds = array_merge($allowedUserIds, $this->groupManager->getUserIdsByRole($pupilRoleId, $group));
                }
            }

            $this->pupilIdsByUserByPermission[$user->getId()][$permission] = $allowedUserIds;
        }

        return $this->pupilIdsByUserByPermission[$user->getId()][$permission];
    }

    /**
     * @param Group[]|\PropelObjectCollection $groups
     * @param User $user
     * @param array $permissions
     * @return array
     */
    protected function filterGroups($groups, User $user, array $permissions)
    {
        $allowedGroupIds = [];
        foreach ($permissions as $permission) {
            $allowedGroupIds = array_merge($allowedGroupIds, $this->getAllowedGroupIds($user, $permission));
        }

        $allowedGroups = [];
        foreach ($groups as $group) {
            if (in_array($group->getId(), $allowedGroupIds)) {
                $allowedGroups[] = $group;
            }
        }

        return $allowedGroups;
    }

    /**
     * @param User[]|\PropelObjectCollection $pupils
     * @param User $user
     * @param array $permissions
     */
    protected function filterUsers($pupils, User $user, array $permissions)
    {
        $allowedPupilIds = [];
        foreach ($permissions as $permission) {
            $allowedPupilIds = array_merge($allowedPupilIds, $this->getAllowedPupilIds($user, $permission));
        }
        $allowedPupils = [];
        foreach ($pupils as $pupil) {
            if (in_array($pupil->getId(), $allowedPupilIds)) {
                $allowedPupils[] = $pupil;
            }
        }

        return $allowedPupils;
    }

}
