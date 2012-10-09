<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\NotificationBundle\Model\om\BaseNotificationType;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class NotificationType extends BaseNotificationType
{
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isCorrection()
	{
		return $this->getIsCorrection();
	}
}