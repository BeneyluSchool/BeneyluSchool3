<?php

namespace BNS\App\NotificationBundle\Notification\MediaLibraryBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 16/02/2015 15:01:24
 */
class MediaLibraryNewMediaNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'MEDIA_LIBRARY_NEW_MEDIA';

    /**
     * @param ContainerInterface $container Services container
     * @param type $media_id
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $mediaId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'media_id' => $mediaId,
            'groupId' => $groupId
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
        $media = MediaQuery::create()->findOneById($objects['media_id']);

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }
        if(!$media)
        {
            $notification->delete();
            return false;
        }



        $user = $media->getUserRelatedByUserId();;
        $finalObjects['%user_fullname%'] = $user ? $user->getFullName(): '';
        $finalObjects['%file_name%'] = $media->getFilename();
        $finalObjects['%media_folder_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMediaLibraryBundle_user_folder', array (
                'slug' => $media->getMediaFolder()->getSlug()
            ));

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
