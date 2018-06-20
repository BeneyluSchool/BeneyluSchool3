<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupTypePeer;


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
class GroupTypePeer extends BaseGroupTypePeer
{
    // use only for old code to replace int with constant
    // use GroupTypePeer::getRoleId($name) for new code
    const ROLE_TEACHER = 7;
    const ROLE_PUPIL = 8;
    const ROLE_PARENT = 9;


    /**
     * @var array|GroupType[]
     */
    protected static $roleTypes = [];

    /**
     * @deprecated
     *
     * @param $params
     * @param string $lang
     * @return GroupType
     * @throws \Exception
     * @throws \PropelException
     */
    public static function createGroupType($params, $lang = null)
    {

        if (null !== $lang) {
            @trigger_error('GroupTypePeer::createGroupType $lang parameter is not used anymore', E_USER_DEPRECATED);
        }

        $groupType = new GroupType();
        $groupType->setId($params['group_type_id']);
        $groupType->setType($params['type']);
        $groupType->setCentralize($params['centralize']);
        $groupType->setSimulateRole($params['simulate_role']);
        if (isset($params['is_recursive'])) {
            $groupType->setIsRecursive($params['is_recursive']);
        }

        $groupType->save();

        return $groupType;

    }

    /**
     * @param string $typeName a Role name (TEACHER, PUPIL, PARENT, ...)
     * @return int|null the id of the Role
     */
    public static function getRoleId($typeName)
    {
        $role = static::getRole($typeName);
        if ($role) {
            return $role->getId();
        }

        return null;
    }

    /**
     * @param string $typeName a Role name (TEACHER, PUPIL, PARENT, ...)
     * @return GroupType|null a Role (GroupType, simulateRole = true)
     */
    public static function getRole($typeName)
    {
        if (!isset(static::$roleTypes[$typeName])) {
            static::$roleTypes[$typeName] = GroupTypeQuery::create()
                ->filterBySimulateRole(true)
                ->filterByType($typeName)
                ->findOne()
            ;
        }

         return static::$roleTypes[$typeName];
    }

    /**
     * @param array $typeNames list of Role name (TEACHER, PUPIL, PARENT, ...)
     * @return array|int[]
     */
    public static function getRoleIds(array $typeNames)
    {
        return array_map(function($item) {
            $item->getId();
        }, static::getRoles($typeNames));
    }

    /**
     * Return Roles
     *
     * @param array $typeNames list of Role name (TEACHER, PUPIL, PARENT, ...)
     * @return GroupType[]
     */
    public static function getRoles(array $typeNames)
    {
        $notCached = array_diff(array_keys(static::$roleTypes), $typeNames);
        if (count($notCached)) {
            $roles = GroupTypeQuery::create()
                ->filterBySimulateRole(true)
                ->filterByType($notCached)
                ->find()
            ;
            /** @var GroupType $role */
            foreach ($roles as $role) {
                static::$roleTypes[$role->getType()] = $role;
            }
        }

        $roles = [];
        foreach ($typeNames as $typeName) {
            if (isset(static::$roleTypes[$typeName]) && static::$roleTypes[$typeName]) {
                $roles[] = static::$roleTypes[$typeName];
            }
        }

        return $roles;
    }


} // GroupTypePeer
