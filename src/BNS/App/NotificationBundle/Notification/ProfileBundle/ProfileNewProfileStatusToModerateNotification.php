<?php

namespace BNS\App\NotificationBundle\Notification\ProfileBundle;

use BNS\App\CoreBundle\Model\ProfileFeedStatusQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 04/06/2018 13:23:17
 */
class ProfileNewProfileStatusToModerateNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'PROFILE_NEW_PROFILE_STATUS_TO_MODERATE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $feedId,  $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'feed_id' => $feedId
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

        $feed = ProfileFeedStatusQuery::create()->findPk($objects['feed_id']);

        if (!$feed) {
            throw new \RuntimeException('Feed id ' . $objects['feed_id'] . ' not found!');
        }

        $finalObjects['%user_fullname%'] = $feed->getProfileFeed()->getProfile()->getUser()->getFullName();
        $finalObjects['%status_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('profile_manager_moderation');
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        
        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
