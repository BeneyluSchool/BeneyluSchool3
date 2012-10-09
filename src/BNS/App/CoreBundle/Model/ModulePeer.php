<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseModulePeer;


/**
 * Skeleton subclass for performing query and update operations on the 'module' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class ModulePeer extends BaseModulePeer {

	public static function createModule($params)
	{
		$module = new Module();
		
		$module->setUniqueName($params['unique_name']);
		$module->setIsContextable($params['is_contextable']);
		$module->setBundleName($params['bundle_name']);
		
		if(isset($params['default_parent_rank']))
			$module->setDefaultParentRank($params['default_parent_rank']);
		if(isset($params['default_pupil_rank']))
			$module->setDefaultPupilRank($params['default_pupil_rank']);
		if(isset($params['default_other_rank']))
			$module->setDefaultOtherRank($params['default_other_rank']);
		
		$module->save();
		$module->setId($params['id']);
		
		foreach($params['i18n'] as $language => $values)
		{
			$module_i18n = new ModuleI18n();
			$module_i18n->setId($module->getId());
			$module_i18n->setLabel($values['label']);
                        $module_i18n->setDescription('');
			if(isset($values['description'])){
				$module_i18n->setDescription($values['description']);
			}
			$module_i18n->setLang($language);
			$module->addModuleI18n($module_i18n);
		} 
		
		$module->save();
		
		return $module;
	}
	
	
} // ModulePeer
