<?php

namespace BNS\App\NotificationBundle\Notification\LiaisonbookBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 22/01/2013 09:59:18
 */
class LiaisonBookNewMessageNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'LIAISON_BOOK_NEW_MESSAGE';

	/**
	 * @param ContainerInterface $container Services container
	 * @param type $message_id
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $message_id, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'message_id' => $message_id,
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

		// Faites les modifications nécessaires à la restitution des paramètres ci-dessous
		// Le container est accessible grâce à l'attribut statique "self::$container"

		$message = LiaisonBookQuery::create('l')
			->where('l.Id = ?', $objects['message_id'])
		->findOne();

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }

		if (null == $message) {
			throw new \RuntimeException('The liaison book message with id : ' . $objects['message_id'] . ' is NOT found !');
		}

		$finalObjects['%message_title%'] = $message->getTitle();
		$finalObjects['%message_url%']	 = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('liaison_book_message', array(
			'slug' => $message->getSlug()
		));

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
