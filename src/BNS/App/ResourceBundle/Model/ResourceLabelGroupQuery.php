<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLabelGroupQuery;

class ResourceLabelGroupQuery extends BaseResourceLabelGroupQuery
{
	public function noRoot()
	{
		return $this->filterByTreeLevel(array('min' => 1));
	}

	public function noUnselectable()
	{
		return $this->filterByIsUserFolder(false);
	}
}