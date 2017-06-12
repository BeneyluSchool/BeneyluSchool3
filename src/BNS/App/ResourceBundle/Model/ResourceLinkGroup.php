<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLinkGroup;

class ResourceLinkGroup extends BaseResourceLinkGroup
{
	const STATUS_ACTIVE = 1;
	const STATUS_DELETED = 0;
	
	/**
	 * @return boolean 
	 */
	public function isActive()
	{
		return $this->getStatus() == self::STATUS_ACTIVE;
	}
	
	/**
	 * @return int
	 */
	public function getResourceLabelId()
	{
		return $this->getResourceLabelGroupId();
	}
	
	/**
	 * @return ResourceLabelGroup 
	 */
	public function getLabel()
	{
		return $this->getResourceLabelGroup();
	}
	
	/**
	 * @return boolean
	 */
	public function isStrongLink()
	{
		return $this->getIsStrongLink();
	}
}