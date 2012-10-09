<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroupQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'resource_label_group' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.ResourceBundle.Model
 */
class ResourceLabelGroupQuery extends BaseResourceLabelGroupQuery {
	
	public function noRoot()
	{
		return $this->filterByTreeLevel(array('min' => 1));
	}
	
	public function noUnselectable()
	{
		return $this->filterByIsUserFolder(false);
	}

} // ResourceLabelGroupQuery
