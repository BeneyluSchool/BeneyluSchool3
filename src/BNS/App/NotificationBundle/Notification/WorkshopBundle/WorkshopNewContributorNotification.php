<?php

namespace BNS\App\NotificationBundle\Notification\WorkshopBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MediaLibraryNewShareNotification
 *
 * @package BNS\App\NotificationBundle\Notification\WorkshopBundle
 */
class WorkshopNewContributorNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'WORKSHOP_NEW_CONTRIBUTOR';

    /**
     * @param ContainerInterface $container Services container
     * @param int $documentId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $documentId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'document_id' => $documentId,
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
        $document = WorkshopDocumentQuery::create()->findOneById($objects['document_id']);

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }

        if (!$document) {
            $notification->delete();
            return false;
        }
        $finalObjects['%document_name%'] = $document->getMedia()->getLabel();
        $finalObjects['%document_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMainBundle_front') . '#/workshop/documents/' . $document->getId();

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
