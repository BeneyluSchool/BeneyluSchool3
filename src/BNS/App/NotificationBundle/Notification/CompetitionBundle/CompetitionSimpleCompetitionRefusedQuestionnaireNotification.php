<?php

namespace BNS\App\NotificationBundle\Notification\CompetitionBundle;

use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 23/06/2017 15:40:46
 */
class CompetitionSimpleCompetitionRefusedQuestionnaireNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'COMPETITION_SIMPLE_COMPETITION_REFUSED_QUESTIONNAIRE';

    /**
     * @param ContainerInterface $container Services container
     * @param int $competitionId
     * @param int $mediaId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $competitionId, $mediaId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'competitionId' => $competitionId,
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
        $questionnaire = CompetitionQuestionnaireQuery::create()->filterByQuestionnaireId($objects['mediaId'])
            ->filterByCompetitionId($objects['competitionId'])
            ->findOne();
        if (!($questionnaire && $questionnaire->getQuestionnaire() && $questionnaire->getCompetition())) {
            return null;
        }
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $finalObjects['%competitionId%'] = $objects['competitionId'];
        $finalObjects['%competitionLabel%'] = $questionnaire->getCompetition()->getTitle();
        $finalObjects['%questionnaireLabel%'] = $questionnaire->getQuestionnaire()->getLabel();
        $finalObjects['%competitionUrl%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppNotificationBundle_redirect', array('applicationUniqueName' => "competition", 'groupId' => $questionnaire->getCompetition()->getGroupId(), 'notification' => 'front_competition')) . '?id=' . $questionnaire->getCompetitionId();
        if ($questionnaire->getCompetition()->getAuthorizeQuestionnaires()) {
            $finalObjects['%secondSentence%'] = parent::$container->get('translator')->trans('COMPETITION_SIMPLE_COMPETITION_REFUSED_QUESTIONNAIRE.COMPETITION_REPROPOSE', [], 'COMPETITION_SIMPLE_COMPETITION_REFUSED_QUESTIONNAIRE');
        } else {
            $finalObjects['%secondSentence%'] = '';
        }

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
