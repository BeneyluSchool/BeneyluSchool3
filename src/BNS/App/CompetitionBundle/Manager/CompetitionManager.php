<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/05/2017
 * Time: 16:01
 */

namespace BNS\App\CompetitionBundle\Manager;


use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionGroupQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionUserQuery;
use BNS\App\CompetitionBundle\Model\ReadingChallenge;
use BNS\App\CompetitionBundle\Model\SimpleCompetition;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Partnership\BNSPartnershipManager;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CompetitionManager
{
    /**
     * @var TokenStorage
     */
    private $tokenStorage;

    /**
     * @var BNSRightManager
     */
    private $rightManager;

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var BNSPartnershipManager
     */
    private $partnershipManager;

    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager, BNSGroupManager $groupManager, BNSUserManager $userManager, BNSPartnershipManager $partnershipManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->rightManager = $rightManager;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->partnershipManager = $partnershipManager;
    }

    public function getPercentCompetition(Competition $competition, $questionnaireId, $bookId, $userId, $groupId)
    {
        $sum = 0;
        $score = 0;
        $number = 0;
        if (CompetitionPeer::CLASSKEY_SIMPLECOMPETITION == $competition->getClassKey()) {

            if (0 != $questionnaireId) {
                $questionnaire = MediaQuery::create()->findPk($questionnaireId);
                $content = $questionnaire->getWorkshopContent();
                $data = $this->getPercentQuestionnaire($content, $questionnaireId, $bookId, $userId, $groupId);
                $questionnaire->percent = $data['percent'];
                $questionnaire->score = $data['score'];
                $sum = $sum + $questionnaire->percent;
                $score += $questionnaire->score;
                $number++;
                $competition->questionnaires[] = $questionnaire;
            } else {
                /** @var SimpleCompetition $competition */
                $questionnaires = $competition->getQuestionnaires();
                foreach ($questionnaires as $questionnaire) {
                    /** @var Media $questionnaire */
                    $content = $questionnaire->getWorkshopContent();
                    $data = $this->getPercentQuestionnaire($content, $questionnaireId, $bookId, $userId, $groupId);
                    $questionnaire->percent = $data['percent'];
                    $questionnaire->score = $data['score'];
                    $sum = $sum + $questionnaire->percent;
                    $score += $questionnaire->score;
                    $number++;
                }
                $competition->questionnaires = $questionnaires;
            }

        } else {
            if (0 != $bookId) {
                $book = BookQuery::create()->findPk($bookId);
                $data = $this->getPercentBook($book, $questionnaireId, $bookId, $userId, $groupId);
                $book->percent = $data['percent'];
                $book->score = $data['score'];
                $books = new \PropelCollection();
                $books->append($book);
                $competition->setBooks($books);
            } else {

                $books = $competition->getBooks();
                foreach ($books as $book) {
                    $data = $this->getPercentBook($book, $questionnaireId, $bookId, $userId, $groupId);
                    $book->percent = $data['percent'];
                    $book->score = $data['score'];
                    $sum = $sum + $book->percent;
                    $score += $book->score;
                    $number++;
                }
                $competition->setBooks($books);
            }
        }
        $competition->percent = $number ? $sum / $number : 0;
        $competition->score = $score;

        return $competition;
    }

    public function getPercentBook(Book $book, $questionnaireId, $bookId, $userId, $groupId)
    {

        $sum = 0;
        $score = 0;
        $number = 0;
        if (0 != $questionnaireId) {
            $questionnaire = MediaQuery::create()->findPk($questionnaireId);
            $content = $questionnaire->getWorkshopContent();
            $data = $this->getPercentQuestionnaire($content, $questionnaireId, $bookId, $userId, $groupId);
            $questionnaire->percent = $data['percent'];
            $questionnaire->score = $data['score'];
            $score += $questionnaire->score;
            $sum = $sum + $questionnaire->percent;
            $number++;
            $book->questionnaires[] = $questionnaire;
        } else {
            $questionnaires = $book->getQuestionnaires();
            foreach ($questionnaires as $questionnaire) {
                /** @var Media $questionnaire */
                $content = $questionnaire->getWorkshopContent();
                $data = $this->getPercentQuestionnaire($content, $questionnaireId, $bookId, $userId, $groupId);
                $questionnaire->percent = $data['percent'];
                $questionnaire->score = $data['score'];
                $score += $questionnaire->score;
                $sum = $sum + $questionnaire->percent;
                $number++;
            }
            $book->questionnaires = $questionnaires;
        }

        return [
            'score' => $score,
            'percent' => $number ? $sum / $number : 0,
        ];
    }

    public function getPercentQuestionnaire(WorkshopContent $content, $questionnaireId, $bookId, $userId, $groupId)
    {

        $pages = $content->getWorkshopDocument()->getWorkshopPages();
        $sumQuestionnaire = 0;
        $scoreQuestionnaire = 0;
        $questionnaireNumberofWidgets = 0;
        foreach ($pages as $page) {
            $widgetGroups = $page->getWorkshopWidgetGroups();
            foreach ($widgetGroups as $widgetgroup) {
                $sum = 0;
                $score = 0;
                $widgetNumber = 0;
                $widgets = $widgetgroup->getWorkshopWidgets();
                foreach ($widgets as $widget) {
                    if (in_array($widget->getType(), ['simple', 'multiple', 'closed', 'gap-fill-text'])) {
                        $data = $this->getPercentWidget($widget, $questionnaireId, $bookId, $userId, $groupId);
                        $widget->percent = isset($data['percent']) ? $data['percent'] : 0;
                        $widget->score = isset($data['score']) ? $data['score'] : 0;
                        $sum = $sum + $widget->percent;
                        $score += $widget->score;
                        $widgetNumber++;
                    }
                }
                if (0 === $widgetNumber) {
                    $widgetgroup->percent = 0;
                } else {
                    $widgetgroup->percent = $sum / $widgetNumber;
                }
                $widgetgroup->score = $score;
                $widgetgroup->setWorkshopWidgets($widgets);
                $sumQuestionnaire = $sumQuestionnaire + $sum;
                $scoreQuestionnaire += $score;
                $questionnaireNumberofWidgets = $questionnaireNumberofWidgets + $widgetNumber;
            }

            $page->setWorkshopWidgetGroups($widgetGroups);
        }

        return [
            'score' => $scoreQuestionnaire,
            'percent' => $questionnaireNumberofWidgets ? $sumQuestionnaire / $questionnaireNumberofWidgets : 0,
        ];
    }

    public function getPercentWidget(WorkshopWidget $widget, $questionnaireId, $bookId, $userId, $groupId)
    {
        if (0 != $userId) {
            $answers = AnswerQuery::create()
                ->filterByWorkshopWidgetId($widget->getId())
                ->useQuestionnaireParticipationQuery()
                ->filterByUserId($userId)
                ->endUse()
                ->select(['score', 'percent'])
                ->findOne();
            return $answers;
        }
        if (0 != $groupId) {
            $group = GroupQuery::create()
                ->findPk($groupId);

            $groupManager = $this->groupManager;
            $groupManager->setGroupById($group->getId());
            $userIds = $groupManager->getUserIdsByRole('PUPIL', $group);
            $answers = AnswerQuery::create()
                ->filterByWorkshopWidgetId($widget->getId())
                ->useQuestionnaireParticipationQuery()
                ->filterByUserId($userIds)
                ->endUse()
                ->select(['score', 'percent'])
                ->findOne();
            return $answers;
        }
        $competition = $widget->getCompetition();
        $participantPupils = $this->getParticipantsPupils($competition, $this->getUser());
        $answers = AnswerQuery::create()
            ->filterByWorkshopWidgetId($widget->getId())
            ->useQuestionnaireParticipationQuery()
                ->useUserQuery()
                    ->filterById($participantPupils, \Criteria::IN)
                ->endUse()
            ->endUse()
            ->select(['score', 'percent'])
            ->find()->toArray();

        $sum = 0;
        $score = 0;
        $count = count($answers);
        foreach ($answers as $answer) {
            $sum += $answer['percent'];
            $score += $answer['score'];
        }

        return [
            'score' => $score,
            'percent' => $count ? $sum / $count : 0,
        ];
    }

    public function exportStats(Competition $competition, Request $request)
    {
        $questionnaireId = (int)$request->get('questionnaire_id');
        $userId = (int)$request->get('user_id');
        $bookId = (int)$request->get('book_id');
        $groupId = (int)$request->get('group_id');
        $stats = $this->getPercentCompetition($competition, $questionnaireId, $bookId, $userId, $groupId)->toStatisticsArray();

        $csv = Writer::createFromString('Titre;Score;Pourcentage;');
        $csv->insertOne([]);
        $csv->setDelimiter(";");
        $csv->insertAll($stats);

        return $csv;
    }

    /**
     * Get all competition I can access from any groups
     *
     * @return array
     */
    public function getCompetitionCanAccessIds($type = 'COMPETITION')
    {
        $rightManager = $this->rightManager;
        if (!$rightManager->hasRightSomeWhere($type . '_ACCESS')) {
            throw new AccessDeniedException('cannot access any competition');
        }
        $competitionFromPupilsIds = array();
        if ($rightManager->hasRight($type . '_ACCESS_BACK')) {
            $groupManageableIds = $this->userManager->getGroupIdsWherePermission($type . '_ACCESS_BACK');
            $pupilsManageable = array();
            foreach ($groupManageableIds as $groupManageableId) {
                $pupils = $this->groupManager->getUserIdsByRole('pupil', $groupManageableId);
                $pupilsManageable = array_merge($pupils, $pupilsManageable);
            }
            $competitionFromPupilsIds = CompetitionUserQuery::create()
                ->filterByUserId($pupilsManageable, \Criteria::IN)
                ->groupByCompetitionId()
                ->select('CompetitionId')
                ->find()
                ->toArray()
            ;
        }
        $groupsIds = $this->userManager->getGroupsIdsUserBelong();
        $groupsRightIds = $this->userManager->getGroupIdsWherePermission($type . '_ACCESS');
        $partnershipsWithRights = GroupQuery::create()->filterById($groupsRightIds, \Criteria::IN)
            ->useGroupTypeQuery()
                ->filterByType('PARTNERSHIP')
            ->endUse()
            ->select("id")
            ->find()
            ->toArray();
        $partnershipMembersIds = $this->partnershipManager->getPartnershipMemberIds($partnershipsWithRights);

        $competitionFromUserGroupsIds = CompetitionQuery::create()
            ->filterByGroupId($groupsIds, \Criteria::IN)
            ->useCompetitionGroupQuery()
                ->filterByGroupId($groupsRightIds, \Criteria::IN)
            ->endUse()
            ->select("id")
            ->find()
            ->toArray();
        $competitionFromUserIds = CompetitionQuery::create()
            ->useCompetitionUserQuery()
                ->filterByUserId($this->rightManager->getUserSessionId())
            ->endUse()
            ->select('id')
            ->find()
            ->toArray();

        $competitionFromPartnershipIds = CompetitionQuery::create()
            ->filterByGroupId($partnershipMembersIds, \Criteria::IN)
            ->useCompetitionGroupQuery()
                ->filterByGroupId($groupsRightIds, \Criteria::IN)
            ->endUse()
            ->select('id')
            ->find()
            ->toArray();

        $competitionIds = array_unique(array_merge($competitionFromUserGroupsIds,$competitionFromPartnershipIds, $competitionFromPupilsIds, $competitionFromUserIds));

        return $competitionIds;
    }

    public function canAccessCompetition (Competition $competition, User $user)
    {
        // TODO rework this
        $participants = $this->getAllParticipants($competition);

        return in_array($user->getId(), $participants);
    }

    public function getParticipantsPupils (Competition $competition, User $user = null)
    {
        $userManager = $this->userManager;
        $users = $competition->getUsers();
        $pupilsIds = [];
        $classroomIds = $userManager->setUser($user)->getGroupsIdsUserBelong();
        foreach ($users as $user) {
            /** @var User $user */
            $mainRole = $userManager->setUser($user)->getMainRole();
            if ($mainRole == 'pupil') {
                $pupilsIds[] = $user->getId();
            }
        }

        $groupIds = $competition->getParticipatingGroupIds();
        $groupPupilsIds = [];

        foreach ($groupIds as $groupId) {
            if (in_array($groupId, $classroomIds)) {
                $currentGroup = $this->groupManager->setGroupById($groupId);
                $currentGroupPupilIds = $currentGroup->getUserIdsByRole('PUPIL', (int)$groupId);
                $groupPupilsIds = array_unique(array_merge($currentGroupPupilIds, $groupPupilsIds));
            }
        }

        return array_unique(array_merge($groupPupilsIds, $pupilsIds));
    }

    public function getAllParticipants (Competition $competition)
    {
        $users = $competition->getUsers();

        $userIds = [];

        foreach ($users as $user) {
            /** @var User $user */
            $userIds[] = $user->getId();
        }

        $groups = $competition->getParticipatingGroups();
        $groupsIds = [];
        $gm = $this->groupManager;

        foreach ($groups as $group) {
            $gm->setGroup($group);
            $ids = $gm->getUsersIds();
            $groupsIds = array_unique(array_merge($ids, $groupsIds));
        }

        return array_unique(array_merge($groupsIds, $userIds));
    }


    public function canViewCompetition (Competition $competition, User $user)
    {
        $this->userManager->setUser($user);
        if ((!(CompetitionPeer::STATUS_PUBLISHED === $competition->getStatus()) && $user->isChild()) || !$this->userManager->hasRightSomeWhere('COMPETITION_ACCESS')) {
            return false;
        }

        $userGroups = $this->userManager->getGroupsUserBelong();

        if (CompetitionUserQuery::create()
            ->filterByUser($user)
            ->filterByCompetition($competition)
            ->count() > 0) {
            // la compétition est dans un groupe auquel le user appartient
            foreach ($userGroups as $group) {
                if ($group->getType() === 'PARTNERSHIP') {
                    $this->groupManager->setGroup($group);
                    if (in_array($competition->getGroupId(), $this->groupManager->getPartnersIds())) {
                        return true;
                    }
                } elseif ($group->getId() === $competition->getGroupId()) {
                    return true;
                }
            }
        }

        $castInt = function ($item) {
            return (int)$item;
        };


        $groupIds = $this->userManager->getGroupIdsWherePermission();
        $groupAccessIds = array_map($castInt, $this->userManager->getGroupIdsWherePermission('COMPETITION_ACCESS'));
        $competitionGroupIds = CompetitionGroupQuery::create()
            ->filterByCompetitionId($competition->getId())
            ->filterByGroupId($groupIds, \Criteria::IN)
            ->select('GroupId')
            ->find()->getArrayCopy();

        $partnershipGroups = $this->partnershipManager->getPartnershipsGroupBelongs($competition->getGroup()->getId());
        $competitionPartnersIds = [];
        foreach ($partnershipGroups as $partnership) {
            $competitionPartnersIds[] = $partnership->getId();
        }

        if (count(array_diff(array_intersect($groupAccessIds, array_map($castInt, $competitionGroupIds)), $competitionPartnersIds)) > 0) {
            return true;
        }

        $partnershipIds = GroupQuery::create()
            ->filterById($groupAccessIds)
            ->useGroupTypeQuery()
                ->filterByType('PARTNERSHIP')
            ->endUse()
            ->select('Id')
            ->find()->getArrayCopy();


        if (count(array_intersect(array_map($castInt,$partnershipIds), $competitionPartnersIds)) > 0) {
            return true;
        }

        $users = $this->getParticipantsPupils($competition, $user);
        $groupBackAccess = $this->userManager->setUser($user)->getGroupsWherePermission('COMPETITION_ACCESS_BACK');
        foreach ($groupBackAccess as $group) {
            $this->groupManager->setGroup($group);
            if (count(array_intersect($users, $this->groupManager->getUsersIds()))) {
                return true;
            }
        }

        return $competition->getUserId() === $user->getId();
    }

    public function canManageCompetition (Competition $competition, User $user)
    {
        $this->userManager->setUser($user);
        if (!$this->userManager->hasRightSomeWhere('COMPETITION_ACCESS_BACK')) {
            return false;
        }

        // Author
        // TODO check rules
        if ($competition->getUserId() === $user->getId()) {
            return true;
        }

        return $this->userManager->hasRight('COMPETITION_ACCESS_BACK', $competition->getGroupId());
    }

    protected function getUser()
    {
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user && $user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
