<?php

namespace BNS\App\NotificationBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\InfoBundle\Manager\AnnouncementManager;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\NotificationBundle\Model\NotificationQuery;
use BNS\App\NotificationBundle\Model\NotificationTypeQuery;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Predis\Client;
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
     * @var string
     */
    private $baseUrl;

    /**
     * @var BNSRightManager
     */
    private $rightManager;

    /**
     * @var AnnouncementManager
     */
    private $announcementManager;

    /**
     * @var Client
     */
    private $redis;
    /**
     * @param Producer $producer
     */
    public function __construct(
        Producer $producer,
        RequestStack $requestStack,
        BNSRightManager $rightManager,
        AnnouncementManager $announcementManager,
        Client $redis,
        $baseUrl
    ) {
        $this->producer = $producer;
        $this->requestStack = $requestStack;
        $this->rightManager = $rightManager;
        $this->announcementManager = $announcementManager;
        $this->redis = $redis;
        $this->baseUrl = $baseUrl;
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
        $parameters = array(
            'group_id' => $notification->getGroupId(),
            'notification_type_unique_name' => $notification->getNotificationTypeUniqueName(),
            'date' => $notification->getDate()->getTimestamp(),
            'objects' => $notification->getObjects(),
            'base_url' => $this->baseUrl
        );

        $excludeUserIds = array();
        // If there is excluded users
        if (null != $excludeUsers) {
            if (is_array($excludeUsers)) {
                foreach ($excludeUsers as $excludeUser) {
                    $excludeUserIds[$excludeUser->getId()] = true;
                }
            } else {
                $excludeUserIds[$excludeUsers->getId()] = true;
            }
        }

        if (is_array($target) || $target instanceof \PropelObjectCollection) {
            $targetIds = array();

            // If user is not excluded and has been active at least once
            /** @var User $user */
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
        } else {
            if (!isset($excludeUserIds[$target->getId()])) {
                $parameters['target_user_id'] = $target->getId();
            } else {
                return false;
            }
        }
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || $request->get('notification', true) === true) {
            $this->producer->publish(serialize($parameters));
        }
    }

    public function getUnreadNotificationNumber(User $user, $resetCache = false)
    {
        $counter = $this->redis->hget('user_' . $user->getUsername(), 'notifications');
        if (null === $counter || $resetCache) {
            $rmu = $this->rightManager->getModulesReachableUniqueNames();
            $notificationTypeUniqueName = NotificationTypeQuery::create()
                ->filterByModuleUniqueName($rmu, \Criteria::IN)
                ->select(['UniqueName'])
                ->find()
                ->getArrayCopy()
            ;
            $counter = NotificationQuery::create('n')
                ->filterByTargetUserId($user->getId())
                ->filterByIsNew(true)
                ->filterByNotificationTypeUniqueName($notificationTypeUniqueName, \Criteria::IN)
                ->count();

            $this->redis->hset('user_' . $user->getUsername(), 'notifications', $counter);
        }

        $counter += $this->announcementManager->countUnreadAnnouncements();

        return $counter;
    }

    public function clearNotificationCache(User $user)
    {
        $this->redis->hdel('user_' . $user->getUsername(), ['notifications']);
    }
}
