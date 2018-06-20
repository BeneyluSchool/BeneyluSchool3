<?php


namespace BNS\App\CompetitionBundle\ApiController;

use BNS\App\CompetitionBundle\Form\Type\CompetitionType;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionUser;
use BNS\App\CompetitionBundle\Model\CompetitionUserQuery;
use BNS\App\CompetitionBundle\Model\PedagogicCourse;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CompetitionBundle\Model\ReadingChallenge;
use BNS\App\CompetitionBundle\Model\SimpleCompetition;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\UserDirectoryBundle\Manager\GroupManager;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CompetitionApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class CompetitionApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="lister les concours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}")
     * @Rest\QueryParam(name="simple_statuses", requirements="(DRAFT|PUBLISHED|FINISHED|,)*", description="Statuses of simple competition")
     * @Rest\QueryParam(name="challenge_statuses", requirements="(DRAFT|PUBLISHED|FINISHED|,)*", description="Statuses of reading challenges")
     * @Rest\QueryParam(name="page", default="1")
     * @Rest\QueryParam(name="type", default="competition")
     * @Rest\QueryParam(name="limit", default="10")
     * @Rest\View(serializerGroups={"Default","competition_list"})
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function indexAction(ParamFetcherInterface $paramFetcher, $id)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK', $id)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if ($paramFetcher->get('simple_statuses')) {
            $simpleStatuses = explode(',', $paramFetcher->get('simple_statuses'));
        } else {
            $simpleStatuses = [];
        }
        if ($paramFetcher->get('challenge_statuses')) {
            $challengeStatuses = explode(',', $paramFetcher->get('challenge_statuses'));
        } else {
            $challengeStatuses = [];
        }
        $type = $paramFetcher->get('type');

        if ('course' === $type) {
            $query = CompetitionQuery::create()
                ->filterByGroupId($id)
                ->lastUpdatedFirst()
                ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE);

        } else {
            $query = CompetitionQuery::create()
                ->filterByGroupId($id)
                ->lastUpdatedFirst()
                ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE,\Criteria::NOT_EQUAL)
                ->applyStatusFilters($simpleStatuses, $challengeStatuses);

        }
        return $this->getPaginator($query, new Route('competition_api_index', [
            'version' => $this->getVersion(),
            'id' => $id,
        ], true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Count competitions by status in the given group",
     *     statusCodes = {
     *      200 = "OK",
     *      403 = "Access denied"
     *     },
     * )
     * @Rest\Get("/{id}/count-status")
     * @Rest\View()
     *
     * @param int $id
     * @return array|View
     */
    public function countByStatusAction($id)
    {
        if (!$this->get('bns.right_manager')->hasRight('COMPETITION_ACCESS_BACK', $id)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }


        $rows = CompetitionQuery::create()
            ->filterByGroupId($id)
            ->groupByStatus()
            ->groupByClassKey()
            ->addAsColumn('count', 'COUNT(*)')
            ->select(['status', 'class_key', 'count'])
            ->find()
        ;

        $statuses = CompetitionPeer::getValueSet(CompetitionPeer::STATUS);
        $typeByClassKey = [
            2 => 'SIMPLE_COMPETITION',
            3 => 'READING_CHALLENGE',
        ];
        $counts = [
            'SIMPLE_COMPETITION' => [],
            'READING_CHALLENGE' => [],
        ];
        foreach ($rows as $row) {
            if (!isset($statuses[$row['status']])) {
                continue;
            }
            if (!isset($typeByClassKey[$row['class_key']])) {
                continue;
            }
            $counts[$typeByClassKey[$row['class_key']]][$statuses[$row['status']]] = $row['count'];
        }

        return $counts;
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="details d'un concours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/details/{id}")
     * @Rest\View(serializerGroups={"Default","competition_list","competition_detail", "competition_statistics", "competition_edit","book_list","book_detail","book_edit","book_statistics","media_basic","user_avatar","competition_participation"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     * @return array
     *
     */
    public function detailsAction(ParamFetcherInterface $paramFetcher, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);

        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        /*return $this->getPaginator($query, new Route('competition_api_index', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);*/
        return $competition;
    }


    /**
     * @ApiDoc(
     *  section="Competition",
     *  resource=true,
     *  description="Création d'un concours et donc de tout ce qui s'en suit",
     *  statusCodes = {
     *      201 = "Concours créé",
     *   },
     *
     * )
     *
     * @Rest\Post("/{type}/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param type
     * @param id
     * @param $request
     * @return Competition|View
     */
    public function postAction(Request $request, $type, $id)
    {
        $rightManager = $this->get('bns.right_manager');

        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK', $id)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        switch ($type) {
            case "simple-competition":
                $competition = new SimpleCompetition();
                break;
            case "reading-challenge":
                $competition = new ReadingChallenge();
                break;
            case "pedagogic-course":
                $competition = new PedagogicCourse();
        }
        $group = GroupQuery::create()->findPk($id);
        if ($group->isPartnerShip()) {
            $classroomsIds = $this->get('bns.partnership_manager')->getPartnershipMemberIds($id);
            foreach ($classroomsIds as $classroomsId) {
                if($rightManager->hasRight('COMPETITION_ACCESS_BACK', $classroomsId)) {
                   $competition->setGroupId($classroomsId);
                }
            }
        } else {
            $competition->setGroupId($id);
        }
        $competition->setUser($this->getUser());

        return $this->handleCompetitionForm($competition, $request, $id);
    }

    protected function handleCompetitionForm(Competition $competition, Request $request, $id)
    {
        if ("PUBLISHED" === $request->get('status')){
            $competition->setPublishedAt(new \DateTime());
        }
        if ("FINISHED" === $request->get('status')){
            $competition->setFinishedAt(new \DateTime());
        }
        $competition->save();

        $authorisedGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('COMPETITION_ACCESS_BACK');
        $partnerships = GroupQuery::create()->filterById($authorisedGroupIds, \Criteria::IN)
            ->useGroupTypeQuery()
            ->filterByType('PARTNERSHIP')
            ->endUse()
            ->select('id')
            ->find()->toArray();

        $memberIds = $this->get('bns.partnership_manager')->getPartnershipMemberIds($partnerships);
        $authorisedGroupIds = array_unique(array_merge($authorisedGroupIds, $memberIds));

        $gm = $this->get('bns.group_manager');

        $authorisedIds = [];
        foreach ($authorisedGroupIds as $groupId) {
            $gm->setGroupById($groupId);
            $authorisedIds = array_unique(array_merge($authorisedIds, $gm->getUsersIds()));
        }

        $requestIds = $request->get('user_ids', []);

        $userIds = [];
        foreach ($requestIds as $userId) {
            if (in_array($userId, $authorisedIds)) {
                $userIds[] = $userId;
            }
        }

        $usersInCompetitionIds = CompetitionUserQuery::create()
            ->filterByUserId($userIds, \Criteria::IN)
            ->filterByCompetition($competition)
            ->select('user_id')
            ->find()->toArray();

        foreach ($userIds as $userId) {
            if (!in_array($userId, $usersInCompetitionIds)) {
                $invitationUser = new CompetitionUser();
                $invitationUser->setCompetitionId($competition->getId())->setUserId($userId)->save();
            }
        }


        CompetitionUserQuery::create()->filterByCompetition($competition)
            ->filterByUserId($userIds, \Criteria::NOT_IN)
            ->find()->delete();


        $requestGroupIds = $request->get('group_ids', []);

        $newGroupIds = [];
        foreach ($requestGroupIds as $groupId) {
            if (in_array($groupId, $authorisedGroupIds)) {
                $newGroupIds[] = $groupId;
            }
        }

        $previousGroups = $competition->getParticipatingGroups();
        $previousGroupIds = array_keys($previousGroups->getArrayCopy('Id'));
        foreach ($newGroupIds as $groupId) {
            if (!in_array($groupId, $previousGroupIds)) {
                $competition->addParticipatingGroup(GroupQuery::create()->findPk($groupId));
            }
        }
        foreach ($previousGroups as $group) {
            if (!in_array($group->getId(), $newGroupIds)) {
                $competition->removeParticipatingGroup($group);
            }
        }

        if ("PUBLISHED" === $request->get('status')) {
            $this->get('bns.competition.notification.manager')->notificatePublishedCompetition($competition);
        }
        $simpleCompetitionManager = $this->get('bns.competition.simple_competition_manager');
        $bookManager = $this->get('bns.competition.book.manager');
        $user = $this->getUser();

        return $this->restForm(new CompetitionType(), $competition, array(
            'csrf_protection' => false,
        ), null, function (Competition $competition) use ($simpleCompetitionManager, $bookManager, $user) {
            $media = MediaQuery::create()->findPk($competition->getMediaId());
            if ($media && MediaManager::STATUS_QUESTIONNAIRE_COMPETITION !== $media->getStatusDeletion()) {
                $mediaCopy = $media->copy();
                $mediaCopy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION)->save();
                $competition->setMedia($mediaCopy);
            }
            $competition->save();

            if ($competition instanceof SimpleCompetition) {
                $simpleCompetitionManager->handleCompetition($competition, $user);
            } else if ($competition instanceof ReadingChallenge || $competition instanceof PedagogicCourse) {
                $competition->getBooks()->save();// help poor propel: persist books before trying to setup their relations
                foreach ($competition->getBooks() as $book) {
                    $bookManager->handleBook($book, $user);
                }
            }

            return $competition;
        });
    }

    /**
     * @ApiDoc(
     *  section="Competition",
     *  resource=true,
     *  description="Editer un concours",
     *  statusCodes = {
     *      201 = "Concours créé",
     *   },
     *
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function editAction(Request $request, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()->findPk($id);
        if ("PUBLISHED" == $request->get('status')){
            $competition->setPublishedAt(new \DateTime());
        }
        if ("FINISHED" == $request->get('status')){
            $competition->setFinishedAt(new \DateTime());
        }
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        return $this->handleCompetitionForm($competition, $request, $id);
    }


    /**
     * @ApiDoc(
     *  section="Competition",
     *  resource=true,
     *  description="suppression d'un concours",
     *)
     * @Rest\Delete("/{id}")
     * @param $id
     * @return view
     */

    public function deleteCompetitionAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()->findPk($id);
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        if ($competition instanceof SimpleCompetition) {
            $mediaIds = MediaQuery::create()
                ->useCompetitionQuestionnaireQuery()
                    ->filterByCompetitionId($id)
                ->endUse()
                ->select('id')
                ->find()
                ->toArray();
            MediaQuery::create()->filterById($mediaIds, \Criteria::IN)
                ->update(["StatusDeletion" => MediaManager::STATUS_DELETED_INT]);
        } else {
            $mediaIds = MediaQuery::create()
                ->useCompetitionBookQuestionnaireQuery()
                    ->useBookQuery()
                        ->filterByCompetitionId($id)
                    ->endUse()
                ->endUse()
                ->select('id')
                ->find()
                ->toArray();
            MediaQuery::create()->filterById($mediaIds, \Criteria::IN)
                ->update(["StatusDeletion" => MediaManager::STATUS_DELETED_INT]);
        }
        $competition->delete();
        return View::create('', Codes::HTTP_OK);

    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="lister les concours Ouverts à a la contribution",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="2")
     * @Rest\Get("/list/contribution")
     * @Rest\View(serializerGroups={"Default","competition_list"})
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function contributionListAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');

        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $simpleCompetitionIds = CompetitionQuery::create()
            ->filterByAuthorizeQuestionnaires(true)
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->select('id')
            ->find()->toArray();
        $readingChallengeIds = CompetitionQuery::create()->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->useBookQuery()
                ->filterByAuthorizeQuestionnaires(true)
                ->_or()
                ->filterByAuthorizeNotices(true)
            ->endUse()
            ->select('id')
            ->find()
            ->toArray();
        $competitionContributionIds = array_merge($simpleCompetitionIds, $readingChallengeIds);
        $query = CompetitionQuery::create()
            ->filterById($competitionContributionIds, \Criteria::IN)
            ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL)
            ->orderByPublishedAt(\Criteria::DESC);

        return $this->getPaginator($query, new Route('competition_api_contribution_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="lister les défis lecture auxquels l'utilisateur a accès",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/reading-challenge")
     * @Rest\View(serializerGroups={"Default","competition_list", "competition_statistics"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="5")
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function readingChallengeListAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $query = CompetitionQuery::create()
            ->filterByClassKey( CompetitionPeer::CLASSKEY_READINGCHALLENGE)
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->orderByPublishedAt(\Criteria::DESC);

        return $this->getPaginator($query, new Route('competition_api_reading_challenge_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="lister les concours simple auxquels l'utilisateur a accès",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/simple-competition")
     * @Rest\View(serializerGroups={"Default","competition_list", "competition_statistics"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="5")
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function simpleCompetitionListAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $query = CompetitionQuery::create()
            ->filterByClassKey( CompetitionPeer::CLASSKEY_SIMPLECOMPETITION)
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->orderByPublishedAt(\Criteria::DESC);

        return $this->getPaginator($query, new Route('competition_api_simple_competition_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }
    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="lister les parcours pédagogiques auxquels l'utilisateur a accès",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/pedagogic-course")
     * @Rest\View(serializerGroups={"Default","competition_list", "competition_statistics"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="5")
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function PedagogicCourseListAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COURSE_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds('COURSE');
        $query = CompetitionQuery::create()
            ->filterByClassKey( CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE)
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->orderByPublishedAt(\Criteria::DESC);

        return $this->getPaginator($query, new Route('competition_api_simple_competition_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le dernier concours publié",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/get/last")
     * @Rest\View(serializerGroups={"Default","competition_list"})
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return Competition
     *
     */
    public function getLastPublishedAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());
        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $lastCompetition = CompetitionQuery::create()
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionIds, \Criteria::IN)
            ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL)
            ->orderByPublishedAt(\Criteria::DESC)
            ->findOne();

        return $lastCompetition;
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le concours le plus populaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/get/like")
     * @Rest\View(serializerGroups={"competition_list_likes"})
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function getMoreLikedAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $mostLiked = CompetitionQuery::create()
            ->filterByStatus(CompetitionPeer::STATUS_PUBLISHED)
            ->filterByClassKey(CompetitionPeer::CLASSKEY_SIMPLECOMPETITION)
            ->filterById($competitionIds, \Criteria::IN)
            ->orderByLike(\Criteria::DESC)
            ->limit(4)
            ->find()
            ->getArrayCopy();

        $mostLiked2 = BookQuery::create()
            ->useCompetitionQuery(null, \Criteria::INNER_JOIN)
                ->filterByClassKey(CompetitionPeer::CLASSKEY_READINGCHALLENGE)
                ->filterByStatus(CompetitionPeer::STATUS_PUBLISHED)
                ->filterById($competitionIds, \Criteria::IN)
            ->endUse()
            ->with('Competition')
            ->orderByLike(\Criteria::DESC)
            ->limit(4)
            ->find()
            ->getArrayCopy()
        ;
        $mostLiked = array_merge($mostLiked, $mostLiked2);

        usort($mostLiked, function($a, $b) {
            if ($a->getLike() === $b->getLike()) {
                // if same like use publacation date
                $aPublished = $a->getPublishedAt('U');
                $bPublished = $b->getPublishedAt('U');
                if ($aPublished === $bPublished) {
                    return 0;
                }

                return $aPublished > $bPublished ? -1 : 1;
            }

            return $a->getLike() > $b->getLike() ? -1 : 1;
        });

        // keep 4 top
        return array_slice($mostLiked, 0, 4);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le concours le plus populaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/participated")
     * @Rest\View(serializerGroups={"Default","competition_list", "competition_statistics"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="5")
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function getParticipatedCompetitionsAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager');
        $userManager->setUser($this->getUser());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $particpated = CompetitionQuery::create()
            ->filterByStatus("PUBLISHED")
            ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL)
            ->filterById($competitionIds, \Criteria::IN)
            ->useCompetitionParticipationQuery()
                ->filterByUserId($user->getId())
            ->endUse();

        return $this->getPaginator($particpated, new Route('competition_api_get_participated_competitions', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }


    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le concours le plus populaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/not-participated")
     * @Rest\View(serializerGroups={"Default","competition_list"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="4")
     * @Rest\QueryParam(name="type", default="competition")
     * @param ParamFetcherInterface $paramFetcher

     *
     * @return array
     *
     */
    public function getNotParticipatedCompetitionsAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager');
        $userManager->setUserById($user->getId());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $competitionParticipate = CompetitionParticipationQuery::create()->filterByUserId($user->getId())->select('competition_id')
        ->find()->toArray();
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $notParticipated = CompetitionQuery::create()
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionParticipate, \Criteria::NOT_IN)
            ->filterById($competitionIds, \Criteria::IN)
            ->orderByPublishedAt(\Criteria::DESC);
        if('course' === $paramFetcher->get('type')) {
            $notParticipated->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE);
        } else {
            $notParticipated->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL);
        }

        return $this->getPaginator($notParticipated, new Route('competition_api_get_not_participated_competitions', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne les concours auxquels l'utilisateur a contribué ",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/list/contributed")
     * @Rest\View(serializerGroups={"Default","competition_list", "competition_statistics"})
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="5")
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array
     *
     */
    public function getContributedCompetitionsAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager');
        $userManager->setUserById($user->getId());

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $contributorSimple = CompetitionQuery::create()
            ->useCompetitionQuestionnaireQuery()
                ->useQuestionnaireQuery()
                    ->useWorkshopContentQuery()
                        ->useWorkshopContentContributorQuery()
                            ->filterByUserId($user->getId())
                        ->endUse()
                    ->endUse()
                ->endUse()
            ->endUse()
            ->select('id')
            ->find()->toArray();

        $authorSimple = CompetitionQuery::create()
            ->useCompetitionQuestionnaireQuery()
                ->useQuestionnaireQuery()
                    ->useWorkshopContentQuery()
                        ->filterByAuthorId($user->getId())
                    ->endUse()
                ->endUse()
            ->endUse()
            ->select('id')
            ->find()->toArray();

        $contributorReading = CompetitionQuery::create()
                ->useBookQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->useQuestionnaireQuery()
                            ->useWorkshopContentQuery()
                                ->useWorkshopContentContributorQuery()
                                    ->filterByUserId($user->getId())
                                ->endUse()
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse()
            ->select('id')
            ->find()->toArray();
        $authorReading = CompetitionQuery::create()
                ->useBookQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->useQuestionnaireQuery()
                            ->useWorkshopContentQuery()
                                ->filterByAuthorId($user->getId())
                            ->endUse()
                        ->endUse()
                    ->endUse()
                ->endUse()
            ->select('id')
            ->find()->toArray();

        $contributor = array_merge($contributorSimple, $contributorReading, $authorReading, $authorSimple);
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $contributed = CompetitionQuery::create()
            ->filterByStatus("PUBLISHED")
            ->filterById($contributor, \Criteria::IN)
            ->filterById($competitionIds, \Criteria::IN)
            ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL)
            ->orderByPublishedAt(\Criteria::DESC);

        return $this->getPaginator($contributed, new Route('competition_api_get_contributed_competitions', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne les concours que l'utilisateur n'a pas fini",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="8")
     * @Rest\Get("/list/not-finished")
     * @Rest\View(serializerGroups={"Default","competition_list"})
     * @param ParamFetcherInterface $paramFetcher
     *
     * @return array|View
     *
     */
    public function getNotFinishedCompetitionsAction(ParamFetcherInterface $paramFetcher)
    {
        $rightManager = $this->get('bns.right_manager');
        $user = $this->getUser();

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Response::HTTP_FORBIDDEN);
        }
        $competitionsParticipatesIds = CompetitionQuery::create()
            ->useCompetitionParticipationQuery()
                ->filterByUserId($user->getId())
            ->endUse()
            ->select('id')
            ->find()->toArray();

        $simpleCompetitionNumberOfQuestionnaires = CompetitionQuestionnaireQuery::create()
            ->filterByCompetitionId($competitionsParticipatesIds, \Criteria::IN)
            ->filterByValidate(1)
            ->groupByCompetitionId()
            ->withColumn('COUNT(*)', 'numberOfQuestionnaires')
            ->select(['competition_id', 'numberOfQuestionnaires'])
            ->find()
            ->toArray();

        $readingChallengeNumberOfQuestionnaires = CompetitionBookQuestionnaireQuery::create()
            ->useBookQuery('book')
                ->filterByCompetitionId($competitionsParticipatesIds, \Criteria::IN)
                ->groupByCompetitionId()
            ->endUse()
            ->filterByValidate(1)
            ->withColumn('COUNT(*)', 'numberOfQuestionnaires')
            ->select(['book.competition_id', 'numberOfQuestionnaires'])
            ->find()
            ->toArray();

        $simpleCompetitionquestionnaireParticipated = QuestionnaireParticipationQuery::create('questionnaire_participation')
            ->filterByUserId($user->getId())
            ->useMediaQuery()
                ->useCompetitionQuestionnaireQuery('competition_questionnaire')
                    ->filterByCompetitionId($competitionsParticipatesIds)
                    ->groupByCompetitionId()
                ->endUse()
            ->endUse()
            ->withColumn('SUM(IF(questionnaire_participation.finished = 1, 1, 0))', 'finishedparticipation')
            ->withColumn('SUM(IF(questionnaire_participation.finished = 0, 1, 0))', 'notfinishedparticipation')
            ->select(['competition_questionnaire.competition_id', 'finishedparticipation', 'notfinishedparticipation'])
            ->find()
            ->toArray();

        $readingChallengequestionnaireParticipated = QuestionnaireParticipationQuery::create('questionnaire_participation')
            ->filterByUserId($user->getId())
            ->useMediaQuery()
                ->useCompetitionBookQuestionnaireQuery()
                    ->useBookQuery('book')
                        ->filterByCompetitionId($competitionsParticipatesIds)
                        ->groupByCompetitionId()
                    ->endUse()
                ->endUse()
            ->endUse()
            ->withColumn('SUM(IF(questionnaire_participation.finished = 1, 1, 0))', 'finishedparticipation')
            ->withColumn('SUM(IF(questionnaire_participation.finished = 0, 1, 0))', 'notfinishedparticipation')
            ->select(['book.competition_id', 'finishedparticipation', 'notfinishedparticipation'])
            ->find()
            ->toArray();


        $competitionNotFinished = array();
        foreach ($simpleCompetitionquestionnaireParticipated as $simpleCompetitionParticipation) {
            if (0 == !(int)$simpleCompetitionParticipation['notfinishedparticipation']) {
                $competitionNotFinished[] = $simpleCompetitionParticipation['competition_questionnaire.competition_id'];
            } else {
                foreach ($simpleCompetitionNumberOfQuestionnaires as $key => $competition) {
                    if ($competition['competition_id'] == $simpleCompetitionParticipation['notfinishedparticipation'] &&
                        (int)$competition['numberOfQuestionnaires'] > ((int)$simpleCompetitionParticipation['finishedparticipation'] + (int)$simpleCompetitionParticipation['notfinishedparticipation'])
                    ) {
                        $competitionNotFinished[] = $simpleCompetitionParticipation['competition_questionnaire.competition_id'];
                    }
                }
            }
        }
        foreach ($readingChallengequestionnaireParticipated as $readingChallengeParticipation) {
            if (0 == !(int)$readingChallengeParticipation['notfinishedparticipation']) {
                $competitionNotFinished[] = $readingChallengeParticipation['book.competition_id'];
            } else {
                foreach ($readingChallengeNumberOfQuestionnaires as $key => $competition) {
                    if ($competition['book.competition_id'] == $readingChallengeParticipation['notfinishedparticipation'] &&
                        (int)$competition['numberOfQuestionnaires'] > ((int)$readingChallengeParticipation['finishedparticipation'] + (int)$readingChallengeParticipation['notfinishedparticipation'])
                    ) {
                        $competitionNotFinished[] = $readingChallengeParticipation['book.competition_id'];
                    }
                }
            }
        }
        $competitionIds = $this->get('bns.competition.competition.manager')->getCompetitionCanAccessIds();
        $competitionToFind = array_intersect($competitionIds, $competitionNotFinished);
        $competitionNotFinished = CompetitionQuery::create()
            ->filterByStatus("PUBLISHED")
            ->filterById($competitionToFind, \Criteria::IN)
            ->filterByClassKey(CompetitionPeer::CLASSKEY_PEDAGOGICCOURSE, \Criteria::NOT_EQUAL)
            ->orderByPublishedAt(\Criteria::DESC);
        return $this->getPaginator($competitionNotFinished, new Route('competition_api_get_not_finished_competitions', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le concours le plus populaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}/statistics")
     * @Rest\View(serializerGroups={"Default","competition_statistics","book_list","book_detail","media_basic","user_avatar"})
     * @param Request $request
     * @param Competition $competition
     *
     * @return array|View
     *
     */
    public function statisticsByCompetitionsAction(Request $request, Competition $competition)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        if (!$competitionManager->canViewCompetition($competition, $this->getUser()) || !$this->get('bns.right_manager')->hasRightSomeWhere('COMPETITION_VIEW_STATS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $competitionManager = $this->get('bns.competition.competition.manager');
        $questionnaireId = (int)$request->get('questionnaire_id');
        $userId = (int)$request->get('user_id');
        $bookId = (int)$request->get('book_id');
        $groupId = (int)$request->get('group_id');
        $reponse = $competitionManager->getPercentCompetition($competition, $questionnaireId, $bookId, $userId, $groupId);
        return $reponse;
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne les classes et élèves participants au concours",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/participants/{competitionId}")
     * @Rest\View(serializerGroups={"Default","competition_statistics","user_avatar"})
     * @param $competitionId
     *
     * @return array
     *
     */
    public function participantsByCompetitionsAction($competitionId)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');

        $competition = CompetitionQuery::create()->findPk($competitionId);

        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $participants = $competitionManager->getParticipantsPupils($competition, $this->getUser());

        return UserQuery::create()
            ->filterById($participants)
            ->find();
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne le nombre de concours par type et status d'un groupe donné",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/numbers/{id}")
     * @Rest\View(serializerGroups={"Default","competition_statistics"})
     *
     *
     * @return array
     *
     */
    public function counterCompetitionAction($id)
    {
        $rightManager = $this->get('bns.right_manager');

        if (!$rightManager->hasRight("COMPETITION_ACCESS_BACK", $id)) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $counters = CompetitionQuery::create()
            ->filterByGroupId($id)
            ->groupByClassKey()
            ->groupByStatus()
            ->withColumn('COUNT(*)', 'count')
            ->select(['class_key', 'status', 'count'])
            ->find()->toArray();
        $response = [];
        foreach ($counters as $counter) {
            if (2 === (int)$counter["class_key"]) {
                $type = "SIMPLE_COMPETITION";
            } else {
                $type = "READING_CHALLENGE";
            }
            switch ($counter["status"]) {
                case 0:
                    $response[$type]["DRAFT"] = (int)$counter["count"];
                    break;
                case 1:
                    $response[$type]["PUBLISHED"] = (int)$counter["count"];
                    break;
                case 2:
                    $response[$type]["FINISHED"] = (int)$counter["count"];
                    break;
            }
        }
        return $response;
    }

    /**
     * @ApiDoc(
     *     section ="Competition",
     *     resource=true,
     *     description="Retourne la classe de l'utilisateur pour le partenariat donné",
     * )
     * @Rest\Get("/partnership/{id}/classroom")
     * @Rest\View(serializerGroups={"Default"})
     * @param $id
     * @return Group|View
     */
    public function classroomForPartnershipAction($id)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK', $id)) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        if (!$this->get('bns.partnership_manager')->setGroupById($id)) {
            return $this->view('', Codes::HTTP_NOT_FOUND);
        }

        $partners = $this->get('bns.partnership_manager')->getPartners();
        foreach ($partners as $partner) {
            if ($rightManager->hasRight('COMPETITION_ACCESS_BACK', $partner->getId())) {
                return $partner;
            }
        }

        return $this->view('', Codes::HTTP_NOT_FOUND);
    }
}
