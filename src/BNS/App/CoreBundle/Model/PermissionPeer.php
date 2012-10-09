<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BasePermissionPeer;


/**
 * Skeleton subclass for performing query and update operations on the 'permission' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class PermissionPeer extends BasePermissionPeer {
	
	public static function createPermission($params)
	{
		$permission = new Permission();
		$permission->setUniqueName($params['unique_name']);
		$permission->setModuleId($params['module_id']);
		
		foreach($params['i18n'] as $language => $values)
		{
			$permission_i18n = new PermissionI18n();
			$permission_i18n->setUniqueName($params['unique_name']);
			$permission_i18n->setLabel($values['label']);
			if(isset($values['description'])){
				$permission_i18n->setDescription($values['description']);
			}
			$permission_i18n->setLang($language);
			$permission->addPermissionI18n($permission_i18n);
		}
		
		$permission->save();
		
		return $permission;
	}

} // PermissionPeer
