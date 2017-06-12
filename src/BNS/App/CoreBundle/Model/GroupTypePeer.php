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


} // GroupTypePeer
