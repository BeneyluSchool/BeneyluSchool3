<?php

namespace BNS\App\NotificationBundle\Notification\BuildersBundle;

use BNS\App\EventBundle\Model\BuildersMessageQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BuildersNewMessageNotification
 *
 * @package BNS\App\NotificationBundle\Notification\BuildersBundle
 */
class BuildersNewMessageNotification extends Notification implements NotificationInterface
{

    const NOTIFICATION_TYPE = 'BUILDERS_NEW_MESSAGE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $messageId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $messageId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'message_id' => $messageId,
        ));
    }

    /**
     * @param Notification $notification
     * @param array $objects notification's parameters
     *
     * @return array notification's translations
     */
    public static function translate(Notification $notification, $objects)
    {

        $message = BuildersMessageQuery::create()->findOneById($objects['message_id']);

        if (!$message) {
            $notification->delete();
            return false;
        }

        $finalObjects = [
            '%user_fullname%' => $message->getUser()->getFullName(),
            '%message_route%' => $notification->getBaseUrl() . self::$container->get('cli.router')->generate('builders_back') . '/messages',
        ];

        return parent::getTranslation($notification, $finalObjects);
    }

}
