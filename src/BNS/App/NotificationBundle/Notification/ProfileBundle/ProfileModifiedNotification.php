<?php

namespace BNS\App\NotificationBundle\Notification\ProfileBundle;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 03/01/2018 11:54:50 
 */
class ProfileModifiedNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'PROFILE_MODIFIED';

    /**
     * @param ContainerInterface $container Services container
     * @param int $userId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $userId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'user_id' => $userId,
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

        $user = UserQuery::create()->findPk($objects['user_id']);

        if (!$user) {
            throw new \RuntimeException('User id ' . $objects['user_id'] . ' not found!');
        }

        $finalObjects['%user_fullname%'] = $user->getFullName();
        $finalObjects['%user_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppClassroomBundle_back_pupil_detail', array(
            'userSlug' => $user->getSlug(),
        ));

        /* 
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
