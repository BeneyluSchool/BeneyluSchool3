<?php

namespace BNS\App\NotificationBundle\Notification\ProfileBundle;

use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 18/10/2017 11:00:02
 */
class ProfileAccountMergedNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'PROFILE_ACCOUNT_MERGED';

    /**
     * @param ContainerInterface $container Services container
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array());
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
        
        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
