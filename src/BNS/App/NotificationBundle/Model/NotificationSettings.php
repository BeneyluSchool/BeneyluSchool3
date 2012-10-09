<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\NotificationBundle\Model\om\BaseNotificationSettings;

/**
 * Skeleton subclass for representing a row from the 'notification_settings' table.
 */
class NotificationSettings extends BaseNotificationSettings
{
	/**
	 * @param string $engine
	 * 
	 * @return boolean 
	 */
	public function isEngine($engine)
	{
		if (null == $this->getNotificationEngine()) {
			return true;
		}
		
		return $engine == $this->getNotificationEngine();
	}
}