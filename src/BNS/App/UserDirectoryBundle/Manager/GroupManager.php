<?php

namespace BNS\App\UserDirectoryBundle\Manager;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Team\BNSTeamManager;

/**
 * Class GroupManager
 *
 * @package BNS\App\UserDirectoryBundle\Manager
 */
class GroupManager
{

    /**
     * @var array
     */
    private $validParentGroupTypes = array(
        'CLASSROOM',
        'SCHOOL',
    );

    /**
     * Group types to be treated as root groups in the hierarchy
     *
     * @var array
     */
    private $rootGroupTypes = array(
        'CLASSROOM',
        'SCHOOL',
        'PARTNERSHIP',
    );

    /**
     * Group types to be treated as subgroups in the hierearchy
     *
     * @var array
     */
    private $subgroupTypes = array(
        'TEAM',
    );

    /**
     * @var BNSTeamManager
     */
    private $teamManager;

    public function __construct(BNSTeamManager $teamManager)
    {
        $this->teamManager = $teamManager;
    }

    /**
     * @return array
     */
    public function getValidParentGroupTypes()
    {
        return $this->validParentGroupTypes;
    }

    /**
     * @param Group $parent
     * @param string $label
     * @param array|User[] $users array of users or user ids
     * @return Group
     */
    public function createTeamGroup(Group $parent, $label = '', $users = array())
    {
        if (!in_array($parent->getType(), $this->getValidParentGroupTypes())) {
            throw new \InvalidArgumentException("Invalid parent type: " . $parent->getType());
        }

        $params = array(
            'type' => 'TEAM',
            'label' => $label ? : 'Team group ' . date('Y-m-d H:i:s'),
        );
        $group = $this->teamManager->createSubgroupForGroup($params, $parent->getId());
        $this->teamManager->setTeam($group);

        if (count($users)) {
            // if given array of id, fetch actual objects
            $first = reset($users);
            if (! $first instanceof User) {
                $users = UserQuery::create()->findPks($users);
            }

            foreach ($users as $user) {
                $this->teamManager->addUser($user);
            }
        }

        return $group;
    }

    /**
     * Parses the given id-indexed Group collection, and builds the directory hierarchy.
     * Optionally, groups with given ids are forced to be root groups.
     *
     * @param array|Group[] $groups
     * @param array $forcedRootIds
     * @return array|Group[]
     */
    public function buildHierarchy($groups, $forcedRootIds = array())
    {
        $hierarchy = array();
        $skip = array();
        foreach ($groups as $group) {
            // ignore invalid root group types
            if (!in_array($group->getType(), $this->rootGroupTypes)) {
                continue;
            }

            // ignore groups to skip
            if (isset($skip[$group->getId()])) {
                continue;
            }

            $subgroups = array();
            // get subgroup infos from API and parse existing local collection to find them
            foreach ($this->teamManager->setGroup($group)->getSubgroups(false, false) as $subgroupInfo) {
                if (isset($groups[$subgroupInfo['id']])) {
                    $subgroup = $groups[$subgroupInfo['id']];

                    // ignore invalid subgroup types
                    if (!in_array($subgroup->getType(), $this->subgroupTypes)) {
                        continue;
                    }

                    $subgroups[] = $subgroup;
                }
            }

            // handle partnerships
            if ($group->isPartnerShip()) {
                // get partners and treat them as subgroups
                foreach ($this->teamManager->setGroup($group)->getPartnersIds() as $partnerId) {
                    if (isset($groups[$partnerId])) {
                        // hide each partner's subgroups
                        $partner = clone $groups[$partnerId];
                        $partner->setSubgroups(array());
                        $subgroups[] = $partner;

                        // partners are classrooms, they are treated as root groups by default: remove them if they are
                        // not forced
                        if (!in_array($partnerId, $forcedRootIds)) {
                            // avoid adding the root group later on
                            $skip[$partnerId] = true;
                            // if already treated, remove it
                            if (isset($hierarchy[$partnerId])) {
                                unset($hierarchy[$partnerId]);
                            }
                        }
                    }
                }
            }
            $group->setSubgroups($subgroups);

            $hierarchy[$group->getId()] = $group;
        }

        return $hierarchy;
    }

    /**
     * Gets the id of the first parent of the given Group.
     *
     * @param Group $group
     * @return int|null
     */
    public function getParentId(Group $group)
    {
        $ids = $this->teamManager->setGroup($group)->getParentsId();

        return isset($ids[0]['id'])
            ? $ids[0]['id']
            : null
        ;
    }

    /**
     * Checks if given Group is a type of subgroup
     *
     * @param Group $group
     * @return bool
     */
    public function isSubgroup(Group $group)
    {
        return in_array($group->getType(), $this->subgroupTypes);
    }

}
