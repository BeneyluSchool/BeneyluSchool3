<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 27/04/2017
 * Time: 12:32
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;

class CompetitionBookQuestionnaireApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Liste les questionnaires en attente de validation",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}/pending-questionnaires")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     */
    public function listPendingQuestionnairesAction(ParamFetcherInterface $paramFetcher, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $pendingBookQuestionnaires = CompetitionBookQuestionnaireQuery::create()
            ->filterByBook($book)
            ->filterByValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)
            ->find();
        foreach ($pendingBookQuestionnaires as $pendingQuestionnaire) {
            $media = $pendingQuestionnaire->getQuestionnaire();
            $media->questionsCount = $media->getWorkshopContent()->getWorkshopDocument()->getWorkshopDocumentQuestionsCount();
        }

        return $pendingBookQuestionnaires;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Liste les questionnaires acceptés",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/accepted-questionnaires/{id}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     */
    public function listAcceptedQuestionnairesAction(ParamFetcherInterface $paramFetcher, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $acceptedQuestionnaires = CompetitionBookQuestionnaireQuery::create()
            ->filterByBook($book)
            ->filterByValidate(CompetitionBookQuestionnaire::VALIDATE_VALIDATED)
            ->find();

        return $acceptedQuestionnaires;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Refuse un questionnaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Patch("/{bookId}/refuse-questionnaire/{id}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param $bookId
     * @param $id
     *
     */
    public function refuseQuestionnaireAction($bookId, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $questionnaire = CompetitionBookQuestionnaireQuery::create()->filterByQuestionnaireId($id)->filterByBookId($bookId)->filterByValidate(0)->findOne();
        if (!$questionnaire) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($questionnaire->getBook()->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $questionnaire->setValidate(CompetitionBookQuestionnaire::VALIDATE_REFUSED)->save();
        $this->get('bns.competition.notification.manager')->notificateRefusedQuestionnaireBook($questionnaire);
        return $questionnaire;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Accepte un questionnaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Patch("/{bookId}/accept-questionnaire/{id}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param $bookId
     * @param $id
     *
     */
    public function acceptQuestionnaireAction($bookId, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $bookQuestionnaire = CompetitionBookQuestionnaireQuery::create()
            ->filterByQuestionnaireId($id)
            ->filterByBookId($bookId)
            ->filterByValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)
            ->findOne();

        if (!$bookQuestionnaire) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($bookQuestionnaire->getBook()->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $media = MediaQuery::create()->findPk($id);
        if (!$media){
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $copy = $media->copy();
        $copy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION);
        $copy->save();
        $content = WorkshopContentQuery::create()
            ->filterByMediaId($id)
            ->findOne();

        $manager = $this->get('bns.workshop.widget_group.manager');
        $contCopy = $manager->duplicateContent($content);
        $contCopy->setMediaId($copy->getId());
        $contCopy->save();

        $groupsContributors = WorkshopContentGroupContributorQuery::create()
            ->filterByContentId($bookQuestionnaire->getQuestionnaire()->getWorkshopContent()->getId())
            ->select('group_id')
            ->find()->toArray();
        $usersContributorsIds = WorkshopContentContributorQuery::create()
            ->filterByContentId($bookQuestionnaire->getQuestionnaire()->getWorkshopContent()->getId())
            ->select('user_id')
            ->find()->toArray();
        foreach ($groupsContributors as $groupContributor) {
            $group = $this->get('bns.group_manager')->setGroupById($groupContributor);
            $groupUsersIds = $group->getUsersIds();
            $usersContributorsIds = array_unique(array_merge($usersContributorsIds, $groupUsersIds));
        }
        foreach ($usersContributorsIds as $userContributor) {
            $user = UserQuery::create()->findPk($userContributor);
            if ($user->isChild()) {
                $participation = CompetitionParticipationQuery::create()->filterByCompetitionId($bookQuestionnaire->getBook()->getCompetitionId())
                    ->filterByUserId($userContributor)->findOneOrCreate();
                $participation->setScore($participation->getScore() + 1)->save();
            }
        }

        $bookQuestionnaire->setValidate(CompetitionBookQuestionnaire::VALIDATE_VALIDATED)->setQuestionnaireId($copy->getId());
        CompetitionBookQuestionnaireQuery::create()
            ->filterByBookId($bookId)
            ->filterByQuestionnaireId($id)
            ->filterByValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)
            ->update(array('Validate' => CompetitionBookQuestionnaire::VALIDATE_VALIDATED, 'QuestionnaireId' => $copy->getId()));

        $this->get('bns.competition.notification.manager')->notificateAcceptedQuestionnaireBook($bookQuestionnaire);
        if ($bookQuestionnaire->getBook()->getAuthorizeAnswers()) {
            $this->get('bns.competition.notification.manager')->notificateNewQuestionnaireBook($bookQuestionnaire);
        }
        return $bookQuestionnaire;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Propose un questionnaire au concours",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Post("/{id}/questionnaires")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     * @param $request
     */
    public function postQuestionnairesPropositionAction(Request $request, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $questionnaireProposed = CompetitionBookQuestionnaireQuery::create()
            ->filterByQuestionnaireId($request->get('mediaId'))
            ->filterByBookId($id)
            ->findOne();
        if ($questionnaireProposed && $questionnaireProposed->getValidate() === CompetitionBookQuestionnaire::VALIDATE_PENDING) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        if ($questionnaireProposed && $questionnaireProposed->getValidate() === CompetitionBookQuestionnaire::VALIDATE_REFUSED) {
            $questionnaireProposed->setValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)->save();
            return $questionnaireProposed;
        }
        $questionnaire = new CompetitionBookQuestionnaire();
        $questionnaire
            ->setBookId($id)
            ->setQuestionnaireId($request->get('mediaId'))
            ->setValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)
            ->setUserId($this->getUser()->getId())
            ->setProposer($this->getUser()->getFullName())
            ->save();

        $this->get('bns.competition.notification.manager')->notificateQuestionnairePropositionBook($questionnaire);
        return $questionnaire;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Liste les questionnaires en attente de validation",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/my-pending-questionnaires/{id}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     */
    public function myListPendingBookQuestionnairesAction(ParamFetcherInterface $paramFetcher, $id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $book = BookQuery::create()
            ->findPk($id);
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $myPendingBookQuestionnaires = CompetitionBookQuestionnaireQuery::create()
            ->filterByBook($book)
            ->filterByUserId($this->getUser()->getId())
            ->filterByValidate(CompetitionBookQuestionnaire::VALIDATE_PENDING)
            ->orderByCreatedAt('DESC')
            ->find();

        return $myPendingBookQuestionnaires;
    }
    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionBookQuestionnaire",
     *     resource=true,
     *     description="Réordonne les questionnaires ",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Patch("/sort")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     *
     * @return array
     */
    public function sortBookQuestionnairesAction(Request $request)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $ids = $request->get('ids', []);
        $book = BookQuery::create()->findPk($request->get('bookId'));
        if (!$book) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($book->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $bookQuestionnaires = CompetitionBookQuestionnaireQuery::create()
            ->filterByBook($book)
            ->orderByRank()
            ->find()->toArray();

        foreach ($bookQuestionnaires as $bookQuestionnaire) {
            if(array_search($bookQuestionnaire['questionnaireId'], $ids)){
                $bookQuestionnaire->setRank(array_search($bookQuestionnaire->getId(), $ids)+1);
            } else {
                return View::create('', Codes::HTTP_BAD_REQUEST);
            }
        }
        $bookQuestionnaires->save();
        return $bookQuestionnaires;
    }
}
