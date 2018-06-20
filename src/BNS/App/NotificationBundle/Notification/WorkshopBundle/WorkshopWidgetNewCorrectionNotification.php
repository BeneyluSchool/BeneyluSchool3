<?php

namespace BNS\App\NotificationBundle\Notification\WorkshopBundle;

use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WorkshopWidgetNewCorrectionNotification
 *
 * @package BNS\App\NotificationBundle\Notification\WorkshopBundle
 */
class WorkshopWidgetNewCorrectionNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'WORKSHOP_WIDGET_NEW_CORRECTION';

    /**
     * @param ContainerInterface $container Services container
     * @param int $widgetId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $widgetId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'widget_id' => $widgetId,
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
        $widget = WorkshopWidgetQuery::create()->findOneById($objects['widget_id']);

        if (!$widget) {
            $notification->delete();
            return false;
        }
        $widgetGroup = $widget->getWorkshopWidgetGroup();
        $page = $widgetGroup->getWorkshopPage();
        $document = $page->getWorkshopDocument();

        $finalObjects['%document_name%'] = $document->getMedia()->getLabel();
        //$finalObjects['%document_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('ng_index', ['rest' => 'app/workshop/documents/'. $document->getId() . '/pages/'. $page->getRank() . '/kit/'. $widgetGroup->getId() . '?annotations=1']);
       // TODO: remplacer les routes pour Angular 5
        $finalObjects['%document_route%'] = sprintf(
            '%s#/workshop/documents/%s/pages/%s/kit/%s?annotations=1',
            $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMainBundle_front'),
            $document->getId(),
            $page->getRank(),
            $widgetGroup->getId()
        );
        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
