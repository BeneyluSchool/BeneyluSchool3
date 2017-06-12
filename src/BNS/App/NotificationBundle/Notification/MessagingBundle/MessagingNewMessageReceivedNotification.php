<?php

namespace BNS\App\NotificationBundle\Notification\MessagingBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 15/09/2012 17:19:15
 */
class MessagingNewMessageReceivedNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'MESSAGING_NEW_MESSAGE_RECEIVED';

	/**
	 * @param ContainerInterface $container
	 * @param int $sender_id
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $sender_id, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'sender_id' => $sender_id,
            'groupId' => $groupId
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

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }
		$sender = UserQuery::create()->findPk($objects['sender_id']);
		if (null == $sender) {
			throw new \InvalidArgumentException('The user with id : ' . $objects['sender_id'] . ' is NOT found !');
		}

		$finalObjects['%sender_full_name%'] = $sender->getFullName();
		$finalObjects['%message_route%']	= $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMessagingBundle_front', array());

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
