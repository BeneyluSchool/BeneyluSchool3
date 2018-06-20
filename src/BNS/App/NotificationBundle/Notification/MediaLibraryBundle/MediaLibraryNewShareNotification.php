<?php

namespace BNS\App\NotificationBundle\Notification\MediaLibraryBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MediaLibraryNewShareNotification
 *
 * @package BNS\App\NotificationBundle\Notification\MediaLibraryBundle
 */
class MediaLibraryNewShareNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'MEDIA_LIBRARY_NEW_SHARE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $mediaId
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
        if (!$media) {
            $notification->delete();
            return false;
        }
        $finalObjects['%classLabel%'] =  self::getGroupLabel($objects);
        $finalObjects['%file_name%'] = $media->getFilename();
        $finalObjects['%media_folder_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMainBundle_front') . '#/media-library/dossiers/' . $media->getMediaFolder()->getSlug();

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
