<?php

namespace BNS\App\NotificationBundle\Notification\MinisiteBundle;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MiniSiteStaticPageModifiedNotification
 *
 * @package BNS\App\NotificationBundle\Notification\MiniSiteBundle
 */
class MinisitePageNewsPublishedNotification extends Notification implements NotificationInterface
{

    const NOTIFICATION_TYPE = 'MINISITE_PAGE_NEWS_PUBLISHED';

    /**
     * @param ContainerInterface $container Services container
     * @param int $pageNewsId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $pageNewsId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'page_news_id' => $pageNewsId,
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

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $pageNews = MiniSitePageNewsQuery::create()->findOneById($objects['page_news_id']);

        if (!$pageNews) {
            $notification->delete();
            return false;
        }

        $finalObjects['%page_title%'] = $pageNews->getTitle();
        $finalObjects['%page_url%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('minisite_page', array(
            'miniSiteSlug' => $pageNews->getMiniSitePage()->getMiniSite()->getSlug(),
            'pageSlug' => $pageNews->getMiniSitePage()->getSlug(),
        ));

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }

}
