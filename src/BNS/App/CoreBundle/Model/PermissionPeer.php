<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BasePermissionPeer;

class PermissionPeer extends BasePermissionPeer
{
	/**
     * @deprecated
	 * @param array $params
	 *
	 * @return \BNS\App\CoreBundle\Model\Permission
	 */
	public static function createPermission($params)
	{
		$permission = new Permission();
		$permission->setUniqueName($params['unique_name']);
		$permission->setModuleId($params['module_id']);

		$permission->save();

		return $permission;
	}
}
