<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseModulePeer;

class ModulePeer extends BaseModulePeer
{
	/**
	 * @param array $params
	 *
	 * @return \BNS\App\CoreBundle\Model\Module
	 */
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
        if(isset($params['default_teacher_rank']))
			$module->setDefaultTeacherRank($params['default_teacher_rank']);
		if(isset($params['default_other_rank']))
			$module->setDefaultOtherRank($params['default_other_rank']);

        $module->setId($params['id']);
        $module->save();

		return $module;
	}
}
