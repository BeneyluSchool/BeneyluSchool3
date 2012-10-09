<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileTemplateQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'profile_template' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileTemplateQuery extends BaseProfileTemplateQuery {

	public static function getAllProfileTemplateCssClasses()
	{
		$profileTemplates = self::create()->find();
		
		$profileTemplatesCssClasses = array();
		foreach ($profileTemplates as $profileTemplate)
		{
			$profileTemplatesCssClasses[] = $profileTemplate->getCssClass();
		}
		
		return $profileTemplatesCssClasses;
	}
	
} // ProfileTemplateQuery
