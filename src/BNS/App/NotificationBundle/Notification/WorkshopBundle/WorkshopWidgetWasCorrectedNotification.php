<?php

namespace BNS\App\NotificationBundle\Notification\WorkshopBundle;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WorkshopWidgetWasCorrectedNotification
 *
 * @package BNS\App\NotificationBundle\Notification\WorkshopBundle
 */
class WorkshopWidgetWasCorrectedNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'WORKSHOP_WIDGET_WAS_CORRECTED';

    /**
     * @param ContainerInterface $container Services container
     * @param int $widgetId
     * @param int $authorId
     */
    public function __construct(ContainerInterface $container, $widgetId, $authorId)
    {
        parent::__construct();
        $this->init($container, null, self::NOTIFICATION_TYPE, array(
            'widget_id' => $widgetId,
            'author_id' => $authorId,
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

        $author = UserQuery::create()->findOneById($objects['author_id']);
        if (!$author) {
            $notification->delete();
            return false;
        }
        $widgetGroup = $widget->getWorkshopWidgetGroup();
        $page = $widgetGroup->getWorkshopPage();
        $document = $page->getWorkshopDocument();

        $finalObjects['%document_name%'] = $document->getMedia()->getLabel();
       // $finalObjects['%document_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')
         //   ->generate('ng_index', ['rest' => 'app/workshop/documents/' . $document->getId() . '/pages/' . $page->getRank() . '/kit/'. $widgetGroup->getId() . '?annotations=1']);
        // TODO: remplacer les routes pour Angular 5
        $finalObjects['%document_route%'] = sprintf(
            '%s#/workshop/documents/%s/pages/%s/kit/%s?annotations=1',
            $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppMainBundle_front'),
            $document->getId(),
            $page->getRank(),
            $widgetGroup->getId()
        );
        $finalObjects['%author_name%'] = $author->getFullName();

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
