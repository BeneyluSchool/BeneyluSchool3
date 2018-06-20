<?php

namespace BNS\App\NotificationBundle\Notification\CompetitionBundle;

use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 23/06/2017 15:48:43
 */
class CompetitionReadingChallengeAcceptedQuestionnaireNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'COMPETITION_READING_CHALLENGE_ACCEPTED_QUESTIONNAIRE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $mediaId
     * @param int $bookId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $mediaId, $bookId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'bookId' => $bookId,
            'mediaId' => $mediaId,
            'groupId' => $groupId
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
        $questionnaire = MediaQuery::create()->findPk($objects['mediaId']);
        if (!($book && $book->getCompetition() && $questionnaire)) {
            return null;
        }
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $finalObjects['%competitionLabel%'] = $book->getCompetition()->getTitle();
        $finalObjects['%questionnaireLabel%'] = $questionnaire->getLabel();
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
