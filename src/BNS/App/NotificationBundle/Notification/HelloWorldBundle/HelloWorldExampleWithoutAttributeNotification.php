<?php

namespace BNS\App\NotificationBundle\Notification\HelloWorldBundle;

use BNS\App\CoreBundle\Model\User;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;

/**
 * Notification generation date : 28/05/2012 11:37:37
 */
class HelloWorldExampleWithoutAttributeNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'HELLO_WORLD_EXAMPLE_WITHOUT_ATTRIBUTE';

	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
		));
	}

	/**
	 * @param Notification $notification
	 * @param array $objects Les paramètres de la notifications
	 *
	 * @return array Les traductions de la notification
	 */
	public static function translate(Notification $notification, $objects)
	{
		$finalObjects = array();

		// Faites les modifications nécessaires à la restitution des paramètres ci-dessous

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
