<?php

namespace BNS\App\ResourceBundle\Model;

use BNS\App\ResourceBundle\Model\om\BaseResourceLinkUser;

class ResourceLinkUser extends BaseResourceLinkUser
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
		return $this->getResourceLabelUserId();
	}
	
	/**
	 * @return ResourceLabelUser 
	 */
	public function getLabel()
	{
		return $this->getResourceLabelUser();
	}
	
	/**
	 * @return boolean
	 */
	public function isStrongLink()
	{
		return $this->getIsStrongLink();
	}
}