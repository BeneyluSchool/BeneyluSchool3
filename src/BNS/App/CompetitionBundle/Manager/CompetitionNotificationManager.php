<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 30/06/2017
 * Time: 17:06
 */

namespace BNS\App\CompetitionBundle\Manager;


use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\BookNotice;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionGroupQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionUserQuery;
use BNS\App\CompetitionBundle\Model\SimpleCompetition;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Manager\NotificationManager;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeAcceptedQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeNewBookNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeNewQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeOpenAnswersNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeProposedNoticeNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeProposedQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengePublishedNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengePublishedWithContributionNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionReadingChallengeRefusedQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionAcceptedQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionNewQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionOpenAnswersNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionProposedQuestionnaireNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionPublishedNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionPublishedWithContributionNotification;
use BNS\App\NotificationBundle\Notification\CompetitionBundle\CompetitionSimpleCompetitionRefusedQuestionnaireNotification;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CompetitionNotificationManager
{
    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var NotificationManager
     */
    protected $notificationManager;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * CompetitionNotificationManager constructor.
     * @param BNSGroupManager $groupManager
     */

    public function __construct(BNSGroupManager $groupManager, NotificationManager $notificationManager, ContainerInterface $container)
    {
        $this->groupManager = $groupManager;
        $this->notificationManager = $notificationManager;
        $this->container = $container;
    }


    public function notificatePublishedCompetition(Competition $competition)
    {

        $usersToSend = $this->getCompetitionParticipants($competition);

        if ($competition instanceof SimpleCompetition) {
            if ($competition->getAuthorizeQuestionnaires()) {
                $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionPublishedWithContributionNotification($this->container, $competition->getId()));
            } else {
                $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionPublishedNotification($this->container, $competition->getId()));
            }
        } else {
            $this->notificationManager->send($usersToSend, new CompetitionReadingChallengePublishedNotification($this->container, $competition->getId()));
        }
    }

    public function notificateOpenAnswersCompetition(SimpleCompetition $competition)
    {
        $usersToSend = $this->getCompetitionParticipants($competition);
        $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionOpenAnswersNotification($this->container, $competition->getId()));
    }


    public function notificateOpenAnswersBook(Book $book)
    {
        $competition = $book->getCompetition();
        $usersToSend = $this->getCompetitionParticipants($competition);
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeOpenAnswersNotification($this->container, $book->getId()));
    }

    public function notificateNewBook(Book $book)
    {
        $competition = $book->getCompetition();
        $usersToSend = $this->getCompetitionParticipants($competition);
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeNewBookNotification($this->container, $book->getId()));
    }

    public function notificateQuestionnairePropositionCompetition(CompetitionQuestionnaire $questionnaire)
    {
        $group = $questionnaire->getCompetition()->getGroup();
        $usersToSendIds = $this->groupManager->getUserIdsByRole('TEACHER', $group);
        $usersToSend = UserQuery::create()->filterById($usersToSendIds, \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionProposedQuestionnaireNotification($this->container, $questionnaire->getCompetitionId(), $questionnaire->getQuestionnaireId()));
    }

    public function notificateQuestionnairePropositionBook(CompetitionBookQuestionnaire $questionnaire)
    {
        $group = $questionnaire->getCompetition()->getGroup();
        $usersToSendIds = $this->groupManager->getUserIdsByRole('TEACHER', $group);
        $usersToSend = UserQuery::create()->filterById($usersToSendIds, \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeProposedQuestionnaireNotification($this->container, $questionnaire->getBookId(), $questionnaire->getQuestionnaireId()));
    }

    public function notificateNoticePropositionBook(BookNotice $notice)
    {
        $group = $notice->getBook()->getCompetition()->getGroup();
        $usersToSendIds = $this->groupManager->getUserIdsByRole('TEACHER', $group);
        $usersToSend = UserQuery::create()->filterById($usersToSendIds, \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeProposedNoticeNotification($this->container, $notice->getBookId(), $notice->getNoticeId()));
    }

    public function notificateAcceptedQuestionnaireCompetition(CompetitionQuestionnaire $questionnaire)
    {
        $contentId = $questionnaire->getQuestionnaire()->getWorkshopContent()->getId();

        $usersToSend = UserQuery::create()->filterById($this->getContentContributorsIds($contentId), \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionAcceptedQuestionnaireNotification($this->container, $questionnaire->getQuestionnaireId()));
    }

    public function notificateAcceptedQuestionnaireBook(CompetitionBookQuestionnaire $questionnaire)
    {
        $contentId = $questionnaire->getQuestionnaire()->getWorkshopContent()->getId();
        $usersToSend = UserQuery::create()->filterById($this->getContentContributorsIds($contentId), \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeAcceptedQuestionnaireNotification($this->container, $questionnaire->getQuestionnaireId(), $questionnaire->getBookId()));
    }

    public function notificateRefusedQuestionnaireCompetition(CompetitionQuestionnaire $questionnaire)
    {
        $contentId = $questionnaire->getQuestionnaire()->getWorkshopContent()->getId();
        $usersToSend = UserQuery::create()->filterById($this->getContentContributorsIds($contentId), \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionRefusedQuestionnaireNotification($this->container, $questionnaire->getCompetitionId(), $questionnaire->getQuestionnaireId()));
    }


    public function notificateRefusedQuestionnaireBook(CompetitionBookQuestionnaire $questionnaire)
    {
        $contentId = $questionnaire->getQuestionnaire()->getWorkshopContent()->getId();
        $usersToSend = UserQuery::create()->filterById($this->getContentContributorsIds($contentId), \Criteria::IN)->find();
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeRefusedQuestionnaireNotification($this->container, $questionnaire->getBookId(), $questionnaire->getQuestionnaireId()));
    }

    public function notificateNewQuestionnaireCompetition(CompetitionQuestionnaire $questionnaire)
    {
        $usersToSend = $this->getCompetitionParticipants($questionnaire->getCompetition());
        $this->notificationManager->send($usersToSend, new CompetitionSimpleCompetitionNewQuestionnaireNotification($this->container, $questionnaire->getCompetitionId(), $questionnaire->getQuestionnaireId()));
    }

    public function notificateNewQuestionnaireBook(CompetitionBookQuestionnaire $questionnaire)
    {
        $usersToSend = $this->getCompetitionParticipants($questionnaire->getCompetition());
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeNewQuestionnaireNotification($this->container, $questionnaire->getBookId(), $questionnaire->getQuestionnaireId()));
    }

    public function notificationNewBook(Book $book)
    {
        $usersToSend = $this->getCompetitionParticipants($book->getCompetition());
        $this->notificationManager->send($usersToSend, new CompetitionReadingChallengeNewBookNotification($this->container, $book->getId()));
    }

    public function getContentContributorsIds($contentId)
    {
        $contributorsIds = WorkshopContentContributorQuery::create()->filterByContentId($contentId)->select('user_id')->find()->toArray();
        $groupContributorIds = WorkshopContentGroupContributorQuery::create()->filterByContentId($contentId)->select('group_id')->find()->toArray();
        foreach ($groupContributorIds as $groupContributorId) {
            $usersIds = $this->groupManager->getUserIdsByRole('PUPIL', $groupContributorId);
            $contributorsIds = array_unique(array_merge($contributorsIds, $usersIds));
        }
        return $contributorsIds;
    }

    public function getCompetitionParticipants($competition)
    {
        $usersParticipantIds = CompetitionUserQuery::create()->filterByCompetition($competition)->select('user_id')->find()->toArray();
        $groupsParticipantsIds = CompetitionGroupQuery::create()->filterByCompetitionId($competition->getId())->select('group_id')->find()->toArray();
        $usersFromGroupsIds = array();
        foreach ($groupsParticipantsIds as $groupsParticipantsId) {
            $usersFromGroupsIds = array_unique(array_merge($usersFromGroupsIds, $this->groupManager->getUserIdsByRole('PUPIL', (int)$groupsParticipantsId)));
            $usersFromGroupsIds = array_unique(array_merge($usersFromGroupsIds, $this->groupManager->getUserIdsByRole('TEACHER', (int)$groupsParticipantsId)));
        }
        $usersToSendIds = array_unique(array_merge($usersFromGroupsIds, $usersParticipantIds));
        $usersToSend = UserQuery::create()->filterById($usersToSendIds, \Criteria::IN)->find();
        return $usersToSend;
    }
}
