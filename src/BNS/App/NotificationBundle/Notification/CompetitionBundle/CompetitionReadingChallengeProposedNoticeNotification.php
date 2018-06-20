<?php

namespace BNS\App\NotificationBundle\Notification\CompetitionBundle;

use BNS\App\CompetitionBundle\Model\BookNoticeQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 23/06/2017 15:53:27
 */
class CompetitionReadingChallengeProposedNoticeNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'COMPETITION_READING_CHALLENGE_PROPOSED_NOTICE';

    /**
     * @param ContainerInterface $container Services container
     * @param type $bookId
     * @param type $mediaId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $bookId, $mediaId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'bookId' => $bookId,
            'mediaId' => $mediaId
        ));
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
        $book = BookQuery::create()->findPk($objects['bookId']);
        if (!$book) {
            return null;
        }
        $notice = BookNoticeQuery::create()->filterByNoticeId($objects['mediaId'])->filterByBookId($book->getId())->findOne();
        if (!$notice) {
            return null;
        }
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $finalObjects['%userName%'] = $notice->getUser()->getFullName();
        $finalObjects['%bookLabel%'] = $book->getTitle();
        $finalObjects['%competitionLabel%'] = $book->getCompetition()->getTitle();
        $finalObjects['%competitionUrl%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppNotificationBundle_redirect', array('applicationUniqueName' => "competition", 'groupId' => $book->getCompetition()->getGroupId(), 'notification' => 'front_competition')) . '?id=' . $book->getCompetitionId();
        $finalObjects['%bookUrl%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppNotificationBundle_redirect',  array('applicationUniqueName' => "competition", 'groupId' => $book->getCompetition()->getGroupId(), 'notification' => 'front_book')) . '?id=' . $book->getCompetitionId() . "&bookId=" . $book->getId();

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
