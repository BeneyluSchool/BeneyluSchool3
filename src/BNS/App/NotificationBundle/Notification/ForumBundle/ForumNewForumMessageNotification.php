<?php
namespace BNS\App\NotificationBundle\Notification\ForumBundle;

use BNS\App\ForumBundle\Model\ForumSubjectQuery;

use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 24/02/2013 14:34:32
 * @author Jérémie Augustin jeremie.augustin@pixel-cookers.com
 */
class ForumNewForumMessageNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'FORUM_NEW_FORUM_MESSAGE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $subjectId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array('subjectId' => $subjectId));
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

        $subject = ForumSubjectQuery::create()->findPk($objects['subjectId']);

        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */
        if ($subject) {
            $finalObjects['%subject_title%'] = $subject->getTitle();
            $finalObjects['%message_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppForumBundle_front_view_subject', array('slug' => $subject->getSlug()));
        } else {
            $finalObjects['%subject_title%'] = 'un forum';
            $finalObjects['%message_route%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppForumBundle_front', array());
        }

        return parent::getTranslation($notification, $finalObjects);
    }
}
