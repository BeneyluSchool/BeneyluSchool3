<?php

namespace BNS\App\NotificationBundle\Notification\CalendarBundle;

use BNS\App\CoreBundle\Model\AgendaEventQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 03/01/2013 16:14:11
 */
class CalendarNewEventNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'CALENDAR_NEW_EVENT';

	/**
	 * @param ContainerInterface $container Services container
	 * @param type $eventId
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $eventId, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'eventId' => $eventId,
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

		$event = self::$container->get('bns.calendar_manager')->getEventById($objects['eventId']);

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }
		$finalObjects['%eventTitle%'] = $event->getTitle();
		$finalObjects['%eventRoute%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppCalendarBundle_front', array(
			'id'	=> $event->getId()
		));

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
