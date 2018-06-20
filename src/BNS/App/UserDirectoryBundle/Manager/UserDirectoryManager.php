<?php

namespace BNS\App\UserDirectoryBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserDirectoryManager
 */
class UserDirectoryManager
{

    const PERMISSION = 'DIRECTORY_ACCESS';

    const CURRENT_GROUP = '__CURRENT__';

    const VIEW_MEDIA_LIBRARY_SHARE = 'media-library-share';
    const VIEW_WORKSHOP_CONTRIBUTORS = 'workshop-contributors';
    const VIEW_MESSAGING_RECIPIENTS = 'messaging-recipients';
    const VIEW_MINISITE_EDITORS = 'minisite-editors';
    const VIEW_CAMPAIGN_RECIPIENTS = 'campaign-recipients';
    const VIEW_HOMEWORK_ASSIGN = 'homework-assign';
    const VIEW_PORTAL_MAILING_LISTS = 'portal-mailing-lists';
    const VIEW_MINISITE_CITY_NEWS = 'minisite-city-news';
    const VIEW_COMPETITION_INVITATIONS = 'competition-invitations';
    const VIEW_LIAISONBOOK_INDIVIDUALIZE = 'liaison-book-individualize';
    const VIEW_FORUM_INVITE = 'forum-invite';
    const VIEW_SEARCH = 'search';
    const VIEW_CALENDAR_EDITORS= 'calendar-editors';

    public $DIRECTORY_GROUP_TYPES = array(
        'SCHOOL',
        'CLASSROOM',
        'TEAM',
        'PARTNERSHIP',
    );

    /**
     * Map of permission name by view
     * @var array
     */
    private $permissionForView = [
        // when browsing directory to select users to share: are visible only the groups where user can manager the
        // media library
        self::VIEW_MEDIA_LIBRARY_SHARE => 'MEDIA_LIBRARY_ADMINISTRATION',

        // when selecting contributor users: are visible only the groups where user has access, with their subgroups
        // if user can manage the workshop
        self::VIEW_WORKSHOP_CONTRIBUTORS => 'WORKSHOP_ACCESS',

        self::VIEW_MESSAGING_RECIPIENTS => 'MESSAGING_ACCESS',

        self::VIEW_MINISITE_EDITORS => 'MINISITE_ACCESS_BACK',

        self::VIEW_CAMPAIGN_RECIPIENTS => 'CAMPAIGN_ACCESS',

        self::VIEW_HOMEWORK_ASSIGN => 'HOMEWORK_ACCESS_BACK',

        self::VIEW_PORTAL_MAILING_LISTS => 'PORTAL_ACCESS_BACK',

        self::VIEW_MINISITE_CITY_NEWS => 'MINISITE_ACCESS_BACK',

        self::VIEW_COMPETITION_INVITATIONS => 'COMPETITION_ACCESS_BACK',

        self::VIEW_LIAISONBOOK_INDIVIDUALIZE => 'LIAISONBOOK_ACCESS_BACK',

        self::VIEW_FORUM_INVITE => 'FORUM_ACCESS_BACK',

        self::VIEW_SEARCH => 'SEARCH_SDET_SEARCH_ENT',

        self::VIEW_CALENDAR_EDITORS => 'CALENDAR_ACCESS_BACK',
    ];

    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    /**
     * @var BNSRightManager
     */
    private $rightManager;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(BNSUserManager $userManager, BNSGroupManager $groupManager, ContainerInterface $container)
    {
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->rightManager = $container->get('bns.right_manager');
        $this->container = $container;
    }

    /**
     * Checks that the given group is visible in the user directory
     *
     * @param Group $group
     * @param string $view
     * @return bool
     */
    public function isVisibleUserDirectoryGroup(Group $group, $view = null)
    {
        if (self::VIEW_CAMPAIGN_RECIPIENTS === $view) {
            return !in_array($group->getType(), ['TEAM', 'PARTNERSHIP']);
        }

        if (in_array($view, [ self::VIEW_PORTAL_MAILING_LISTS, self::VIEW_MINISITE_CITY_NEWS])) {
            return 'CITY' === $group->getType();
        }

        return in_array($group->getType(), $this->DIRECTORY_GROUP_TYPES);
    }

    /**
     * @param User $user
     * @param string $view
     * @return array|Group[]
     */
    public function getGroupsWhereAccess(User $user, $view = null)
    {
        $permission = null;
        $groups = [];

        $permissions = $this->getGroupPermissionsForView($view);

        // special case where only current group must be visible
        if (self::CURRENT_GROUP === $permissions) {
            $currentGroup = $this->rightManager->getCurrentGroup();

            return [$currentGroup->getId() => $currentGroup];
        }

        // no permission found => no access
        if (!is_array($permissions)) {
            return [];
        }

        // get groups where user has permissions
        $directGroups = $this->userManager->setUser($user)->getGroupsWherePermissions($permissions);
        foreach ($directGroups as $group) {
            // keep only meaningful/visible groups
            if (!$this->isVisibleUserDirectoryGroup($group, $view)) {
                continue;
            }

            // special case: keep only current group and partnerships
            if (self::VIEW_COMPETITION_INVITATIONS === $view
                && !$group->isPartnerShip()
                && $group->getId() !== $this->rightManager->getCurrentGroupId()
            ) {
                continue;
            }

            if (self::VIEW_CAMPAIGN_RECIPIENTS === $view && $group !== $this->rightManager->getCurrentGroup()) {
                continue;
            }

            $groups[$group->getId()] = $group;
        }

        $directIds = array_keys($directGroups);
        foreach ($directGroups as $group) {
            // if partnership, get all partners without checking their permissions (partnership permissions have the
            // priority
            if ($group->isPartnerShip()) {
                $group->setSubgroups($this->getOtherPartners($group, $directIds));
            }
        }

        return $groups;
    }

    public function getSubgroupsWhereAccess(Group $group, User $user, $view = null)
    {
        $permissions = $this->getGroupPermissionsForView($view, true);

        // no permission found => no access
        if (!is_array($permissions)) {
            return [];
        }

        if ($group->isPartnerShip()) {
            return $this->getOtherPartners($group);
        }

        $canSeeClassrooms = true;
        $visibleTypes = [];   // whitelist
        $hiddenTypes = [];    // blacklist
        if (self::VIEW_CAMPAIGN_RECIPIENTS === $view) {
            // campaign: all subgroups except TEAM and maybe CLASSROOM are visible
            $hiddenTypes = ['TEAM'];
            if (!$this->userManager->setUser($user)->hasRight('CAMPAIGN_VIEW_CLASSROOM', $group->getId())) {
                $hiddenTypes[] = 'CLASSROOM';
            }
        } else {
            // default view: only TEAM subgroups are visible
            $visibleTypes = ['TEAM'];
        }

        $subgroups = [];

        // get child groups if user has permission
        foreach ($permissions as $permission) {
            if ((self::VIEW_CAMPAIGN_RECIPIENTS === $view && in_array($this->rightManager->getCurrentGroupId(), $this->groupManager->setGroup($group)->getUniqueAncestorIds())) || $this->userManager->setUser($user)->hasRight($permission, $group->getId())) {
                foreach ($this->groupManager->setGroup($group)->getSubgroups() as $subgroup) {
                    // keep only meaningful/visible groups
                    if ($subgroup->getGroupType()->getSimulateRole()) {
                        continue; // ignore role subgroups
                    }
                    if (count($visibleTypes) && !in_array($subgroup->getType(), $visibleTypes)) {
                        continue; // whitelist enabled, group does not match
                    }
                    if (count($hiddenTypes) && in_array($subgroup->getType(), $hiddenTypes)) {
                        continue; // blacklist enabled, group matches
                    }

                    $subgroups[$subgroup->getId()] = $subgroup;
                }
            }
        }

        return $subgroups;
    }

    /**
     * Gets all other partners of the given partnership, ie groups where user has no direct access.
     *
     * @param Group $partnership
     * @param array $excludedIds
     * @return array|Group[]
     */
    public function getOtherPartners(Group $partnership, $excludedIds = null)
    {
        if (!$partnership->isPartnerShip()) {
            throw new \InvalidArgumentException('Not a partnership!');
        }
        if (!is_array($excludedIds)) {
            $excludedIds = array_keys($this->rightManager->getRights());
        }
        $partners = [];
        foreach ($this->groupManager->setGroup($partnership)->getPartners() as $partner) {
            if (!in_array($partner->getId(), $excludedIds)) {
                $partners[] = $partner;
            }
        }

        return $partners;
    }

    /**
     * @param Group $group
     * @param string $view
     * @return array
     */
    public function getUserIdsByRoles(Group $group, $view = null)
    {
        if (in_array($view, [self::VIEW_PORTAL_MAILING_LISTS, self::VIEW_MINISITE_CITY_NEWS])) {
            return [];
        }

        $directAccess = !!count($this->rightManager->getRightsInGroup($group->getId()));
        $partnershipAccess = false;

        // no right in group, check if from partnership
        if (!$directAccess) {
            $partnerships = $this->container->get('bns.partnership_manager')->getPartnershipsGroupBelongs($group->getId());
            foreach ($partnerships as $partnership) { /** @var Group $partnership */
                if (count($this->rightManager->getRightsInGroup($partnership->getId()))) {
                    $partnershipAccess = $partnership;
                    break;
                }
            }
        }

        $userIdsByRole = array();
        if (!$this->rightManager->isAuthenticated()) {
            return $userIdsByRole;
        }

        $hideParents = false;
        $hideChildren = false;
        $hideOhter = false;
        $permission = null;
        if (self::VIEW_MEDIA_LIBRARY_SHARE === $view) {
            // when browsing directory to select users to share: are visible only the users who have their own
            // media library
            $permission = 'MEDIA_LIBRARY_MY_MEDIAS';
        } else if (self::VIEW_WORKSHOP_CONTRIBUTORS === $view) {
            // when browsing directory to select workshop contributors: are visible only users who have access
            // to the workshop
            $permission = 'WORKSHOP_ACCESS';
        } else if (self::VIEW_MESSAGING_RECIPIENTS === $view) {
            // when browsing directory to select messaging recipients: are visible all users in the group that
            // have access to the messaging
            $permission = 'MESSAGING_ACCESS';
            if ($partnershipAccess) {
                $hideParents = true; // parents are not messageable in partnership groups
            }
        } else if (self::VIEW_MINISITE_EDITORS === $view) {
            // when browsing directory to select minisite editors: are visible only users who have access
            // to the minisite back
            $permission = 'MINISITE_ACCESS_BACK';
        } else if (self::VIEW_CAMPAIGN_RECIPIENTS === $view) {
            // when browsing to select campaign recipients: are visible all users except children
            $hideChildren = true;
        } else if (self::VIEW_HOMEWORK_ASSIGN === $view) {
            // when browsing to assign homework: are visible only children
            $hideParents = true;
            $hideOhter = true;
        } else if (self::VIEW_COMPETITION_INVITATIONS === $view) {
            // when browsing directory to select competition invitations: are visible only the pupils
            $hideParents = true;
        } elseif (self::VIEW_LIAISONBOOK_INDIVIDUALIZE === $view) {
            $hideParents = true;
            $hideOhter = true;
        } elseif (self::VIEW_FORUM_INVITE === $view) {
            $permission = 'FORUM_ACCESS';
        } elseif (self::VIEW_SEARCH === $view) {
            $permission = 'PROFILE_ACCESS_BACK';
            $hideParents = true;
        } else if (self::VIEW_CALENDAR_EDITORS === $view) {
            // when browsing to select calendar editors: are visible only children
            $hideParents = true;
            $hideOhter = true;
        }
        else {
            // in default view, parents are not visible
            $hideParents = true;
        }
        $redis = $this->container->get('snc_redis.default');
        if (self::VIEW_CAMPAIGN_RECIPIENTS === $view && $redis->get('user_directory:' . $view . ':group_' . $group->getId()) !== null) {
            return unserialize($redis->get('user_directory:' . $view . ':group_' . $group->getId()));
        }
        if ($permission) {
            if ($partnershipAccess) {
                // get partnership subgroups (roles) that have the needed permission
                $subgroups = [];
                $userIds = [];
                $roleGroups = $this->groupManager->setGroup($partnershipAccess)->getSubgroups();
                foreach ($roleGroups as $roleGroup) {
                    $partnershipPermissions = $this->groupManager->getPermissionsForRole($partnershipAccess, $roleGroup->getGroupType());
                    if (in_array($permission, $partnershipPermissions)) {
                        $subgroups[] = $roleGroup;
                        $userIds = array_merge($userIds, $this->groupManager->setGroup($group)->getUsersByRoleUniqueNameIds($roleGroup->getType()));
                    }
                }
            } else {
                // load users filtered by the guessed permission
                $userIds = $this->groupManager->setGroup($group)->getUsersByPermissionUniqueName($permission);
                $userIds = array_keys($userIds);
            }
        } else {
            // no specific permission: load all user ids
            $userIds = $this->groupManager->setGroup($group)->getUsersIds();
            $userIds = array_keys(array_flip($userIds)); // convert string to int...
        }

        if (count($userIds) && count($userIds) <= 1000) {
            $userIds = $this->ensureAccessibleUserIds($userIds);
        }

        $mainRole = $this->rightManager->getUserManager()->getMainRole();

        // subgroups have not been calculated previously: simply get them
        if (!isset($subgroups)) {
            $subgroups = $this->groupManager->setGroup($group)->getSubgroups();
        }

        // iterate over each role subgroup, to group user ids by role
        foreach ($subgroups as $subgroup) {
            if ($subgroup->getGroupType()->getSimulateRole() || !$subgroup->getGroupType()->getIsRecursive()) {
                $roleUserIds = $this->groupManager->getUserIdsByRole($subgroup->getType(), $group);

                // hide parents if necessary
                if ($hideParents && 'PARENT' === $subgroup->getType()) {
                    $roleUserIds = array();
                }

                // hide children if necessary
                if ($hideChildren && 'PUPIL' === $subgroup->getType()) {
                    $roleUserIds = array();
                }

                // hide other roles if necessary
                if ($hideOhter && !in_array($subgroup->getType(), ['PUPIL', 'PARENT'])) {
                    $roleUserIds = array();
                }

                // overrides for child users
                if ($this->rightManager->isChild()) {
                    // always see own parents only
                    if ('PARENT' === $subgroup->getType()) {
                        $roleUserIds = $this->filterOnlyOwnParents($roleUserIds);
                    }

                // overrides for parent users
                } else if ('parent' === $mainRole) {
                    // always see only own children when not in default view
                    if ('PUPIL' === $subgroup->getType() && $permission) {
                        $roleUserIds = $this->filterOnlyOwnChildren($roleUserIds);
                    }

                    // never see other parents
                    if ('PARENT' === $subgroup->getType()) {
                        $roleUserIds = array();
                    }
                }

                // find all users that are visible and have current role
                $visibleRoleUserIds = array_values(array_intersect($userIds, $roleUserIds));
                $subgroup->setUsers($visibleRoleUserIds);

                // aggregate user ids for the current role
                $userIdsByRole[$subgroup->getType()] = isset($userIdsByRole[$subgroup->getType()])
                    ? array_unique(array_merge($userIdsByRole[$subgroup->getType()], $visibleRoleUserIds))
                    : $visibleRoleUserIds;
            }
        }

        foreach ($userIdsByRole as $role => $ids) {
            if (!count($ids) || count($ids) > 1000) {
                continue;
            }
            $userIdsByRole[$role] = $this->ensureAccessibleUserIds($ids);
        }
        if (self::VIEW_CAMPAIGN_RECIPIENTS === $view) {
            $redis->set('user_directory:' . $view . ':group_' . $group->getId(), serialize($userIdsByRole));
            $redis->expire('user_directory:' . $view . ':group_' . $group->getId(), 86400);
        }
        return $userIdsByRole;
    }

    /**
     * @param Group $group
     * @param string $view
     * @return array
     */
    public function countUserIdsByRoles(Group $group, $view = null)
    {
        $userIdsByRole = $this->getUserIdsByRoles($group, $view);
        foreach ($userIdsByRole as $role => $ids) {
            $userIdsByRole[$role] = count($ids);
        }

        return $userIdsByRole;
    }

    /**
     * Filter the given list of ids to keep only children of the current user
     *
     * @param array $userIds
     * @return array
     */
    public function filterOnlyOwnChildren($userIds)
    {
        $childrenIds = array();

        if (count($userIds)) {
            /** @var User $child */
            foreach ($this->rightManager->getUserManager()->getUser()->getChildren() as $child) {
                if (in_array($child->getId(), $userIds)) {
                    $childrenIds[] = $child->getId();
                }
            }
        }

        return $childrenIds;
    }

    /**
     * Filter the given list of ids to keep only parents of the current user
     *
     * @param array $userIds
     * @return array
     */
    public function filterOnlyOwnParents($userIds)
    {
        $parentsIds = array();

        if (count($userIds)) {
            /** @var User $parent */
            foreach ($this->rightManager->getUserManager()->getUser()->getParents() as $parent) {
                if (in_array($parent->getId(), $userIds)) {
                    $parentsIds[] = $parent->getId();
                }
            }
        }

        return $parentsIds;
    }

    /**
     * Filter the given list of user ids to retain only users that are not archived.
     *
     * @param array $ids
     * @return array
     */
    public function ensureAccessibleUserIds($ids)
    {
        $accessibleIds = UserQuery::create()
            ->filterByArchived(false)
            ->filterById($ids)
            ->orderByLastName()
            ->orderByFirstName()
            ->select(['Id'])
            ->find()
        ;

        // convert string to int
        return array_keys(array_flip($accessibleIds->toArray()));
    }

    /**
     * Gets the user's profile url.
     *
     * @param User $user
     * @return string The user profile url, or an empty string
     */
    public function getProfileUrl(User $user)
    {
        return $this->container->get('router')->generate('BNSAppProfileBundle_view_profile', array(
            'userSlug' => $user->getSlug(),
        ));
    }

    /**
     * Gets the pupils of the given group, limited by the given number. Set it to null for no limit.
     *
     * @param Group $group
     * @param int $number
     * @return array|mixed|\PropelObjectCollection|User[]
     */
    public function getPupilsPreview(Group $group, $number = 3)
    {
        $userIdsByRole = $this->getUserIdsByRoles($group);
        $pupilIds = isset($userIdsByRole['PUPIL']) ? $userIdsByRole['PUPIL'] : [];

        return UserQuery::create()
            ->filterById($pupilIds)
            ->orderByLastName()
            ->orderByFirstName()
            ->limit($number)
            ->find();
    }

    /**
     * Gets the classrooms of the given school group, limited by the given number. Set it to null for no limit.
     *
     * @param Group $group
     * @param int $number
     * @return array|mixed|\PropelObjectCollection|Group[]
     */
    public function getClassroomsPreview(Group $group, $number = 3)
    {
        if ($group->getType() !== 'SCHOOL') {
            return null;
        }

        $classrooms = $this->groupManager->setGroup($group)->getSubgroupsByGroupType('CLASSROOM');

        return array_slice($classrooms, 0, $number);
    }

    public function getPermissionForView($view)
    {
        if (isset($this->permissionForView[$view])) {
            return $this->permissionForView[$view];
        }

        return null;
    }

    /**
     * Gets the required permission(s) that give access to groups (or subgroups) for the given view.
     *
     * @param string $view
     * @param bool $subgroup Whether to get permission for subgroups instead of groups
     * @return array|false  - false: The group is NOT visible in the given view
     *                      - array: The required permissions. Can be an array, containing an empty value, meaning that
     *                               no particular permission is needed, any right in the group will do.
     */
    private function getGroupPermissionsForView($view = null, $subgroup = false)
    {
        $permission = $this->getPermissionForView($view);
        switch ($view) {
            // when browsing directory to select users to share: are visible only the groups where user can manager the
            // media library
            case self::VIEW_MEDIA_LIBRARY_SHARE:
                return $subgroup ? false : [$permission];

            case self::VIEW_COMPETITION_INVITATIONS:
                return [$permission];

            // when selecting contributor users: are visible only the groups where user has access, with their subgroups
            // if user can manage the workshop
            case self::VIEW_WORKSHOP_CONTRIBUTORS:
                return $subgroup ? ['WORKSHOP_ACTIVATION'] : [$permission];

            case self::VIEW_MESSAGING_RECIPIENTS:
                return $subgroup ? false : [$permission];

            case self::VIEW_MINISITE_EDITORS:
                return $subgroup ? false : [$permission];

            case self::VIEW_CAMPAIGN_RECIPIENTS:
                return [$permission];

            case self::VIEW_HOMEWORK_ASSIGN:
                return [$permission];
            case self::VIEW_SEARCH:
                return [$permission];

            case self::VIEW_CALENDAR_EDITORS:
                return [$permission];

            case self::VIEW_PORTAL_MAILING_LISTS:
            case self::VIEW_MINISITE_CITY_NEWS:
            case self::VIEW_LIAISONBOOK_INDIVIDUALIZE:
                return self::CURRENT_GROUP;

            // when using the directory in consultation: are visible all groups where user has access, with their
            // subgroups if user can manage them
            default:
                return $subgroup ? ['CLASSROOM_ACCESS_BACK', 'SCHOOL_ACCESS_BACK'] : [null];
        }
    }

    /**
     * Gets all groups and subgroups for the given User and permissions.
     * The first set of permissions is used to retrieve "root" groups. By default groups where user has any right are
     * retrieved.
     * The second set of permission is used to retrieve subgroups of these root groups. By default no subgroups are
     * retrieved.
     *
     * @deprecated Very low performance
     *
     * @param User $user
     * @param string|array $permissions One (or more) permissions for "root" groups.
     * @param string|array $subgroupPermissions On (or more) permissions for subgroups.
     * @return array|Group[]
     */
    private function getGroupsAndSubgroupsForPermissions(User $user, $permissions = array(), $subgroupPermissions = array())
    {
        if (!is_array($permissions)) {
            $permissions = array($permissions);
        }
        if (!is_array($subgroupPermissions)) {
            $subgroupPermissions = array($subgroupPermissions);
        }

        $groups = array();

        // get groups where user has permissions
        foreach ($this->userManager->setUser($user)->getGroupsWherePermissions($permissions) as $group) {
            $groups[$group->getId()] = $group;

            // also get child groups if user has permission
            foreach ($subgroupPermissions as $subgroupPermission) {
                if ($this->userManager->hasRight($subgroupPermission, $group->getId())) {
                    foreach ($this->groupManager->setGroup($group)->getSubgroups() as $subgroup) {
                        $groups[$subgroup->getId()] = $subgroup;
                    }
                }
            }

            // if partnership, get all partners without checking their permissions (partnership permissions have the
            // priority
            if ($group->isPartnerShip()) {
                foreach ($this->groupManager->setGroup($group)->getPartners() as $partner) {
                    $groups[$partner->getId()] = $partner;
                }
            }
        }

        return $groups;
    }

}
