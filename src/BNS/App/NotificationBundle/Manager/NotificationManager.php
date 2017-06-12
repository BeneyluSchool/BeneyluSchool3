<?php

namespace BNS\App\NotificationBundle\Manager;

use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class NotificationManager
{
	private $producer;

    /**
     * @var RequestStack
     */
    private $requestStack;

	/**
	 * @param type $producer
	 */
	public function __construct($producer, RequestStack $requestStack)
	{
		$this->producer = $producer;

        $this->requestStack = $requestStack;
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\User[] $target
	 * @param Notification $notification
	 * @param null $excludeUsers
	 * @param bool $force
	 * @return bool|void
	 */
	public function send($target, Notification $notification, $excludeUsers = null, $force = false)
	{
        $baseUrl = BNSAccess::getCurrentUrl();

		$parameters = array(
			'group_id'						=> $notification->getGroupId(),
			'notification_type_unique_name'	=> $notification->getNotificationTypeUniqueName(),
			'date'							=> $notification->getDate()->getTimestamp(),
			'objects'						=> $notification->getObjects(),
            'base_url'                      => $baseUrl
		);

		$excludeUserIds = array();
		// If there is excluded users
		if (null != $excludeUsers) {
			if (is_array($excludeUsers)) {
				foreach ($excludeUsers as $excludeUser) {
					$excludeUserIds[$excludeUser->getId()] = true;
				}
			}
			else {
				$excludeUserIds[$excludeUsers->getId()] = true;
			}
		}

		if (is_array($target) || $target instanceof \PropelObjectCollection) {
			$targetIds = array();

			// If user is not excluded and has been active at least once
			foreach ($target as $user) {
				if (!isset($excludeUserIds[$user->getId()]) && (null !== $user->getLastConnection() || $force)) {
					$targetIds[] = $user->getId();
				}
			}

			// Aucun destinataire
			if (count($targetIds) == 0) {
				return;
			}

			$parameters['targets_user_id'] = $targetIds;
		}
		else {
			if (!isset($excludeUserIds[$target->getId()])) {
				$parameters['target_user_id'] = $target->getId();
			}
			else {
				return false;
			}
		}
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->get('notification', true) === true) {
            $this->producer->publish(serialize($parameters));
        }
	}
}
