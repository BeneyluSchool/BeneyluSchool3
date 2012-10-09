<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseRolePeer;


/**
 * Skeleton subclass for performing query and update operations on the 'role' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class RolePeer extends BaseRolePeer {
	
	public static function createRole($params)
	{
		$role = new Role();
		$role->save();
		$role->setId($params['role_id']);
		if(isset($params['unique_name']))
			$role->setUniqueName($params['unique_name']);
		$role->save();
		
		foreach($params['i18n'] as $language => $values)
		{
			$role_i18n = new RoleI18n();
			$role_i18n->setId($role->getId());
			$role_i18n->setLabel($values['label']);
			if(isset($values['description'])){
				$role_i18n->setDescription($values['description']);
			}
			$role_i18n->setLang($language);
			$role_i18n->save();
			$role->addRoleI18n($role_i18n);
			$role->save();
		}
		return $role;
	}
	
	public static function getRolesArray(){
		$all_roles = RoleQuery::create()->find()->toArray();
		
		foreach($all_roles as $role){
			$return[$role['Id']] = $role['UniqueName'];
		}
		
		return $return;
	}
	
	

} // RolePeer
