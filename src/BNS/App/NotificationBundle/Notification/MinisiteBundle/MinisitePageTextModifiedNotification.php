<?php

namespace BNS\App\NotificationBundle\Notification\MinisiteBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MiniSiteStaticPageModifiedNotification
 *
 * @package BNS\App\NotificationBundle\Notification\MiniSiteBundle
 */
class MinisitePageTextModifiedNotification extends Notification implements NotificationInterface
{

    const NOTIFICATION_TYPE = 'MINISITE_PAGE_TEXT_MODIFIED';

    /**
     * @param ContainerInterface $container Services container
     * @param int $pageId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $pageId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'page_id' => $pageId,
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
        $pageText = MiniSitePageTextQuery::create()->findOneByPageId($objects['page_id']);

        if (!$pageText) {
            $notification->delete();
            return false;
        }

        $finalObjects['%classLabel%'] =  self::getGroupLabel($objects);
        $finalObjects['%page_title%'] = $pageText->getPublishedTitle();
        $finalObjects['%page_url%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('minisite_page', array(
            'miniSiteSlug' => $pageText->getMiniSitePage()->getMiniSite()->getSlug(),
            'pageSlug' => $pageText->getMiniSitePage()->getSlug(),
        ));

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }

}
