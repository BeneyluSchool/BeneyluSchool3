<?php

namespace BNS\App\NotificationBundle\Notification\MessagingBundle;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;

/**
 * Notification generation date : 15/09/2012 17:19:15 
 */
class MessagingNewMessageReceivedNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'MESSAGING_NEW_MESSAGE_RECEIVED';
	
	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param type $sender_id 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $sender_id, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
			'sender_id' => $sender_id,
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
		
		$sender = UserQuery::create()->findPk($objects['sender_id']);
		if (null == $sender) {
			throw new \InvalidArgumentException('The user with id : ' . $objects['sender_id'] . ' is NOT found !');
		}
		
		$finalObjects['%sender_slug%'] = $sender->getSlug();
		$finalObjects['%sender_full_name%'] = $sender->getFullName();
		$finalObjects['%message_route%'] = \BNS\App\CoreBundle\Access\BNSAccess::getContainer()->get('router')->generate('BNSAppMessagingBundle_front', array(), true);
				
		/* 
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */
		
		return parent::getTranslation($notification, $finalObjects);
	}
}