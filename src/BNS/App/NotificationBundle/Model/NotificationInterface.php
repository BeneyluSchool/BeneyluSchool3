<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\NotificationBundle\Model\Notification;

/**
 * @author Sylvain Lorinet 
 */
interface NotificationInterface
{
	/**
	 * @param string $string 
	 */
	public static function translate(Notification $notification, $objects);
}