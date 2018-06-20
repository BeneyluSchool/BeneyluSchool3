<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 06/04/2017
 * Time: 10:11
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\AnswerPeer;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\BookParticipation;
use BNS\App\CompetitionBundle\Model\BookParticipationQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipation;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopQuestionnaireQuery;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionnaireParticipationApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition-Questionnaire-Participation",
     *     resource=true,
     *     description="lister les participations par questionnaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail", "questionnaire_participation"})
     * @param $id
     *
     * @return array|view
     *
     */
    public function indexAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $doc = MediaQuery::create()
            ->findPk($id);
        if (!$doc) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $competition = $doc->getCompetition();
        if (!$competition) {
            if ($book = $doc->getbook()) {
                $competition = $book->getCompetition();
            }
        }
        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $participations = QuestionnaireParticipationQuery::create()
            ->filterByQuestionnaireId($id)
            ->find();

        return $participations;
    }

    /**
     * @ApiDoc(
     *     section ="Competition-Questionnaire-Participation",
     *     resource=true,
     *     description="Détail d'une participation",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/get/{id}")
     * @Rest\View(serializerGroups={"Default","detail", "questionnaire_participation"})
     * @param $id
     *
     * @return QuestionnaireParticipation|View
     *
     */
    public function getAction($id)
    {
        // check access
        $competition = $this->getCompetitionByQuestionnaireId($id);
        $bookParticipation = null;

        $competitionParticipation = CompetitionParticipationQuery::create()
            ->filterByCompetitionId($competition->getId())
            ->filterByUserId($this->getUser()->getId())
            ->findOneOrCreate();
        if ($competitionParticipation->isNew()) {
            $competitionParticipation->save();
        }

        if (CompetitionPeer::CLASSKEY_READINGCHALLENGE == $competition->getClassKey()) {
            $book = BookQuery::create()
                ->useCompetitionBookQuestionnaireQuery()
                    ->filterByQuestionnaireId($id)
                ->endUse()
                ->findOne();
            if (!$book) {
                throw $this->createNotFoundException();
            }
            $bookParticipation = BookParticipationQuery::create()
                ->filterByUser($this->getUser())
                ->filterByBook($book)
                ->findOneOrCreate();
            if ($bookParticipation->isNew()) {
                $bookParticipation->save();
            }
        }

        try {
            $participation = $this->getUserParticipationByQuestionnaireId($id);

            // current page number is invalid, reset it
            if ($participation->getPage() > $participation->getMedia()->getWorkshopContent()->getWorkshopDocument()->countWorkshopPages()) {
                $participation->setPage(1)->save();
            }

            if ($bookParticipation) {
                $participation->setGlobalLike($bookParticipation->getLike());
            } else {
                $participation->setGlobalLike($competitionParticipation->getLike());
            }

            $code = Codes::HTTP_OK;
        } catch (NotFoundHttpException $e) {
            $participation = new QuestionnaireParticipation();
            $participation
                ->setUserId($this->getUser()->getId())
                ->setQuestionnaireId($id)
                ->setLastTryStartedAt(new \DateTime())
                ->save()
            ;

            if ($bookParticipation) {
                $participation->setGlobalLike($bookParticipation->getLike());
            } else {
                $participation->setGlobalLike($competitionParticipation->getLike());
            }
            $code = Codes::HTTP_CREATED;
        }

        if ($code) {
            $competitionQuestionnaire = $participation->getMedia()->getCompetitionQuestionnaires()->getFirst();
            if (!$competitionQuestionnaire) {
                $competitionQuestionnaire = $participation->getMedia()->getCompetitionBookQuestionnaires()->getFirst();
            }
            if ($competitionQuestionnaire) {
                $participation->getMedia()->maxAttemptsNumber = $competitionQuestionnaire->getAttemptsNumber();
            }

            return $participation;
        }

        return $this->view('', Response::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Competition-Questionnaire-Participation",
     *  resource=true,
     *  description="passage d'une page à l'autre dans le questionnaire",
     *  statusCodes = {
     *      200 = "réponse ajoutée",
     *   },
     *
     * )
     *
     * @Rest\Patch("/page/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $id
     */
    public function patchPageAction($id)
    {
        // check access
        $this->getCompetitionByQuestionnaireId($id);
        $participation = $this->getUserParticipationByQuestionnaireId($id);

        $questionnaireId = WorkshopDocumentQuery::create()
            ->useWorkshopContentQuery()
                ->useMediaQuery()
                    ->filterById($participation->getQuestionnaireId())
                ->endUse()
            ->endUse()
            ->select('id')
            ->findOne();
        if (!$questionnaireId){
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $answersscores = AnswerQuery::create("Answer")->filterByWorkshopDocumentId($questionnaireId)
                                                ->useQuestionnaireParticipationQuery()
                                                    ->filterByUserId($this->getUser()->getId())
                                                ->endUse()
                                                ->withColumn('SUM('. AnswerPeer::SCORE .')', 'sumscore')
                                                ->select('sumscore')
                                                ->findOne();
        $participation->setPage($participation->getPage()+1)->setScore($answersscores)->save();
        return $participation;
    }


    /**
     * @ApiDoc(
     *  section="Competition-Questionnaire-Participation",
     *  resource=true,
     *  description="nouvelle participation à un questionnaire (pas la premiere)",
     *  statusCodes = {
     *      201 = "réponse ajoutée",
     *   },
     *
     * )
     *
     * @Rest\Patch("/new/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $id
     */
    public function patchNewAction($id)
    {
        // check access
        $this->getCompetitionByQuestionnaireId($id);
        $participation = $this->getUserParticipationByQuestionnaireId($id);

        $questionnaire = $this->getCompetitionQuestionnaireByQuestionnaireId($id);
        if ($participation && $questionnaire->getAttemptsNumber() && $participation->getTryNumber() >= $questionnaire->getAttemptsNumber()) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $participation
            ->setScore(0)
            ->setPage(1)
            ->setFinished(false)
            ->setTryNumber($participation->getTryNumber()+1)
            ->setLastTryStartedAt(new \DateTime())
            ->save();

        // delete previous answser
        AnswerQuery::create()->filterByParticipationId($participation->getId())->delete();

        return $participation;
    }

    /**
     * @ApiDoc(
     *  section="Competition-Questionnaire-Participation",
     *  resource=true,
     *  description="Liker un questionnaire ",
     *  statusCodes = {
     *      201 = "Questionnaire liké",
     *   },
     *
     * )
     *
     * @Rest\Patch("/like/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function patchLikeAction($id)
    {
        $competition = $this->getCompetitionByQuestionnaireId($id);
        // cannot like if not participation exist
        $this->getUserParticipationByQuestionnaireId($id);

        if (CompetitionPeer::CLASSKEY_READINGCHALLENGE == $competition->getClassKey()) {
            // Like book
            $book = BookQuery::create()
                ->useCompetitionBookQuestionnaireQuery()
                    ->filterByQuestionnaireId($id)
                ->endUse()
                ->findOne()
            ;
            if (!$book) {
                throw $this->createNotFoundException();
            }
            /** @var BookParticipation $BookParticipation */
            $BookParticipation = BookParticipationQuery::create()
                ->filterByUser($this->getUser())
                ->filterByBook($book)
                ->findOne();

            if (!$BookParticipation) {
                throw $this->createNotFoundException();
            }

            if ($BookParticipation->getLike()) {
                // already liked
                return $this->view(null, Response::HTTP_NO_CONTENT);
            }
            $BookParticipation->setLike(true)->save();

            // update global book counter
            // TODO make it concurent friendly
            $book->setLike($book->getLike() + 1)->save();
        } else {
            // Or like competition
            $competitionParticipation = CompetitionParticipationQuery::create()
                ->filterByUser($this->getUser())
                ->filterByCompetition($competition)
                ->findOne();
            if (!$competitionParticipation) {
                throw $this->createNotFoundException();
            }

            if ($competitionParticipation->getLike()) {
                // already liked
                return $this->view(null, Response::HTTP_NO_CONTENT);
            }

            $competitionParticipation->setLike(true)->save();

            // update global competition counter
            // TODO make it concurent friendly
            $competition->setLike($competition->getLike() + 1)->save();
        }

        return $this->view();
    }

    /**
     * @ApiDoc(
     *  section="Competition-Questionnaire-Participation",
     *  resource=true,
     *  description="Finir un questionnaire ",
     *  statusCodes = {
     *      201 = "Questionnaire fini",
     *   },
     *
     * )
     *
     * @Rest\Patch("/finish/{id}")
     * @Rest\View(serializerGroups={"participation_finished"})
     */
    public function patchFinishAction($id)
    {
        // check access
        $this->getCompetitionByQuestionnaireId($id);
        $participation = $this->getUserParticipationByQuestionnaireId($id);

        $participation->setFinished(true)->save();

        return $participation;
    }

    /**
     * @param $id
     * @return Competition
     */
    protected function getCompetitionByQuestionnaireId($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionPeer::getCompetitionByQuestionnaireId($id);
        if (!$competition) {
            throw $this->createNotFoundException();
        }
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $competition;
    }

    /**
     * @param $id
     * @return QuestionnaireParticipation
     */
    protected function getUserParticipationByQuestionnaireId($id)
    {
        $participation = QuestionnaireParticipationQuery::create()
            ->filterByQuestionnaireId($id)
            ->filterByUser($this->getUser())
            ->findOne()
        ;
        if (!$participation){
            throw $this->createNotFoundException();
        }

        return $participation;
    }

    /**
     * @param $id
     * @return CompetitionQuestionnaire|CompetitionBookQuestionnaire
     */
    protected function getCompetitionQuestionnaireByQuestionnaireId($id) {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competitionQuestionnaire = CompetitionPeer::getCompetitionQuestionnaireByQuestionnaireId($id);
        if (!$competitionQuestionnaire) {
            throw $this->createNotFoundException();
        }
        if (!$competitionManager->canViewCompetition($competitionQuestionnaire->getCompetition(), $this->getUser())) {
            throw $this->createAccessDeniedException();
        }

        return $competitionQuestionnaire;
    }
}
