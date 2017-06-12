<?php

namespace BNS\App\NotificationBundle\Notification\ProfileBundle;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 16/02/2015 15:01:24
 */
class ProfileAssistanceStartedNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'PROFILE_ASSISTANCE_STARTED';

    /**
     * @param ContainerInterface $container Services container
     * @param type $assistant_user_id
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $assistant_user_id, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'assistant_user_id' => $assistant_user_id,
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
        $finalObjects = array();

        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $finalObjects['%assistant_user_id%'] = $objects['assistant_user_id'];
        $user = UserQuery::create()->findPk($objects['assistant_user_id']);
        $finalObjects['%assistant_fullname%'] = $user ? $user->getFullName(): '';
        $finalObjects['%profile_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppProfileBundle_back');

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
