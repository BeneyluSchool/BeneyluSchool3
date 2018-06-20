<?php

namespace BNS\App\NotificationBundle\Notification\CompetitionBundle;

use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\NotificationBundle\Model\Notification;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 23/06/2017 15:39:59
 */
class CompetitionSimpleCompetitionOpenAnswersNotification extends Notification implements NotificationInterface
{
    const NOTIFICATION_TYPE = 'COMPETITION_SIMPLE_COMPETITION_OPEN_ANSWERS';

    /**
     * @param ContainerInterface $container Services container
     * @param type $competitionId
     * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
     */
    public function __construct(ContainerInterface $container, $competitionId, $groupId = null)
    {
        parent::__construct();
        $this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
            'competitionId' => $competitionId,
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
        $competition = CompetitionQuery::create()->findPk($objects['competitionId']);
        if (!$competition) {
            return null;
        }
        // Faites les modifications nécessaires à la restitution des paramètres ci-dessous
        // Le container est accessible grâce à l'attribut statique "self::$container"
        $finalObjects['%competitionId%'] = $objects['competitionId'];
        $finalObjects['%competitionLabel%'] = $competition->getTitle();
        $finalObjects['%competitionUrl%'] = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('BNSAppNotificationBundle_redirect', array('applicationUniqueName' => "competition", 'groupId' => $competition->getGroupId(), 'notification' => 'front_competition')) . '?id=' . $competition->getId();

        /*
         * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
         * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
         * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
         */

        return parent::getTranslation($notification, $finalObjects);
    }
}
