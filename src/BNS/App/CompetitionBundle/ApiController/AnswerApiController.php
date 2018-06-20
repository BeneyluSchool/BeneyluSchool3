<?php


namespace BNS\App\CompetitionBundle\ApiController;

use BNS\App\CompetitionBundle\Form\Type\AnswerType;
use BNS\App\CompetitionBundle\Model\Answer;
use BNS\App\CompetitionBundle\Model\AnswerPeer;
use BNS\App\CompetitionBundle\Model\AnswerQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipation;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BookNoticeApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class AnswerApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition-Answer",
     *     resource=true,
     *     description="lister les réponses par questionnaire",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     *
     */
    public function indexAction(ParamFetcherInterface $paramFetcher, $id)
    {

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $query = AnswerQuery::create()
            ->filterByWorkshopDocumentId($id)
            ->groupByWorkshopWidgetId()
            ->find();

        return $query;
        /*return $this->getPaginator($query, new Route('answer_api_index', array(
            'id' => $id,
            'version' => $this->getVersion()
        ), true), $paramFetcher);*/
    }

    /**
     * @ApiDoc(
     *  section="Competition-Answer",
     *  resource=true,
     *  description="Ajout d'une réponse à une question",
     *  statusCodes = {
     *      201 = "réponse ajoutée",
     *   },
     *
     * )
     *
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $request
     */
    public function postAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        if (!is_integer($request->get('workshop_widget_id'))) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        if (is_null($request->get('answer'))) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        $widgetId = $request->get('workshop_widget_id');
        $data = $request->get('answer');


        /** @var $doc WorkshopDocument */
        $doc = WorkshopDocumentQuery::create()
            ->useWorkshopPageQuery()
                ->useWorkshopWidgetGroupQuery()
                    ->useWorkshopWidgetQuery()
                        ->filterById($widgetId)
                    ->endUse()
                ->endUse()
            ->endUse()
            ->findOne();
        if (!$doc) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $competition = CompetitionQuery::create()
            ->useCompetitionQuestionnaireQuery()
                ->filterByQuestionnaireId($doc->getMediaId())
            ->endUse()
            ->findOne();

        if (!$competition) {
            $competition = CompetitionQuery::create()
                ->useBookQuery()
                    ->useCompetitionBookQuestionnaireQuery()
                        ->filterByQuestionnaireId($doc->getMediaId())
                    ->endUse()
                ->endUse()
                ->findOne();
        }

        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $competitionManager = $this->get('bns.competition.competition.manager');
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $widget = WorkshopWidgetQuery::create()->findPk($widgetId);
        if (!$widget) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $answer = AnswerQuery::create()
            ->filterByWorkshopWidgetId($widgetId)
            ->useQuestionnaireParticipationQuery()
                ->filterByUserId($this->getUser()->getId())
            ->endUse()
            ->findOne();

        if (!$answer) {
            $answer = new Answer();
        }
        $answer->setWorkshopDocument($doc);

        $participation = QuestionnaireParticipationQuery::create()
            ->filterByQuestionnaireId($doc->getMediaId())
            ->filterByUserId($this->getUser()->getId())
            ->findOne();

        if (!$participation) {
            throw $this->createNotFoundException();
        }

        $answer->setQuestionnaireParticipation($participation);
        $questionnaireManager = $this->get('bns.workshop.questionnaire.manager');

        $answersVerified = $questionnaireManager->verifyAnswer($data, $widget, $widget->getType(), true);
        $scoreAndPercent = $this->get('bns.competition.answer.manager')->calculateScoreAndPercent($widget, $request->get('answer'), $answersVerified);
        $score = $scoreAndPercent["score"];
        $percent = $scoreAndPercent["percent"];
        $answer->setAnswer($data);
        $answer->setWorkshopWidgetId($widgetId);
        $answer
            ->setScore($score)
            ->setPercent($percent)
            ->save();
//        $this->restForm(new AnswerType($answer), $answer, array(
//            'csrf_protection' => false,
//        ));

        $answersScores = AnswerQuery::create()
            ->filterByWorkshopDocumentId($doc->getId())
            ->useQuestionnaireParticipationQuery(null, \Criteria::INNER_JOIN)
                ->filterByUserId($this->getUser()->getId())
            ->endUse()
            ->withColumn('SUM('. AnswerPeer::SCORE .')', "sumscore")
            ->select('sumscore')
            ->findOne();

        $participation->setScore($answersScores)->save();

        return $answersVerified;
    }

    /**
     * @ApiDoc(
     *  section="Competition-Answer",
     *  resource=true,
     *  description="modification d'une réponse à une question",
     *  statusCodes = {
     *      201 = "réponse ajoutée",
     *   },
     *
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $request
     */
    public function patchAction(Request $request, $id)
    {
        $rightManager = $this->get('bns.right_manager');

        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $answer = AnswerQuery::create()->filterByWorkshopWidgetId($id)
            ->useQuestionnaireParticipationQuery()
            ->filterByUserId($this->getUser()->getId())
            ->endUse()
            ->findOne();

        return $this->restForm(new AnswerType($answer), $answer, array(
            'csrf_protection' => false,
        ));
    }

    /**
     * @ApiDoc(
     *     section ="Competition-Answer",
     *     resource=true,
     *     description="lister les réponses par questionnaire de l'utilisateur en cours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/list/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     *
     */
    public function listAction(ParamFetcherInterface $paramFetcher, $id)
    {

        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $query = AnswerQuery::create()
            ->filterByWorkshopDocumentId($id)
            ->useQuestionnaireParticipationQuery()
            ->filterByUserId($this->getUser()->getId())
            ->endUse()
            ->groupByWorkshopWidgetId()
            ->find();

        return $query;
        /*return $this->getPaginator($query, new Route('answer_api_index', array(
            'id' => $id,
            'version' => $this->getVersion()
        ), true), $paramFetcher);*/
    }

    /**
     * @ApiDoc(
     *     section ="Competition-Answer",
     *     resource=true,
     *     description="Affiche la réponse donnée d'une question",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/get/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param ParamFetcherInterface $paramFetcher
     * @param $id
     *
     * @return array
     *
     */

    public function getAction($id)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $query = AnswerQuery::create()
            ->filterByWorkshopWidgetId($id)
            ->orderByCreatedAt('DESC')
            ->useQuestionnaireParticipationQuery()
            ->filterByUserId($this->getUser()->getId())
            ->endUse()
            ->findOne();
        //on récupère la réponse de l'utilisateur courant à la question
        if (!$query) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        return $query;
    }

    /**
     * @ApiDoc(
     *     section ="Competition-Answer",
     *     resource=true,
     *     description="lister les réponses par question",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/widget/{id}")
     * @param $id
     *
     * @return JsonResponse
     *
     */
    public function GetAnswersByWidgetAction($id)
    {
        $answerManager = $this->get('bns.competition.answer.manager');
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('COMPETITION_ACCESS_BACK')) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $query = AnswerQuery::create()
            ->filterByWorkshopWidgetId($id)
            ->find();
        $widget = WorkshopWidgetQuery::create()->findPk($id);
        $response = $answerManager->compareAnswers($query, $widget);
        return New JsonResponse($response);
    }
}
