<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 28/04/2017
 * Time: 17:03
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\BookParticipation;
use BNS\App\CompetitionBundle\Model\BookParticipationQuery;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\StoreBundle\Client\Message\Request;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookParticipationApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition-BookParticipation",
     *     resource=true,
     *     description="lister les participations d'un concours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","book_detail"})
     * @param $id
     *
     *
     */
    public function indexAction($id)
    {
        if ($this->checkFoundAndAccessBook($id)) {
            $bookParticipation = BookParticipationQuery::create()
                ->filterByBookId($id)
                ->lastUpdatedFirst()
                ->find();

            return $bookParticipation;
        }
        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Competition-BookParticipation",
     *  resource=true,
     *  description="Ajout d'une participation à un Concours",
     *  statusCodes = {
     *      201 = "participation ajoutée",
     *   },
     *
     * )
     *
     * @Rest\Post("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $id
     */
    public function postAction($id)
    {
        if ($this->checkFoundAndAccessBook($id)) {
            $participation = new BookParticipation();
            $participation->setUser($this->getUser())
                ->setBookId($id)
                ->setScore(0)
                ->save();

            return View::create('', Codes::HTTP_CREATED);
        }
        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Competition-BookParticipation",
     *  resource=true,
     *  description="Mise à jour d'une participation à un Concours",
     *  statusCodes = {
     *      201 = "participation mise à jour",
     *   },
     *
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $request
     * @param $id
     */
    public function patchAction($id)
    {
        if ($this->checkFoundAndAccessBook($id)) {
            BookParticipationQuery::create()
                ->findPk($id)
                ->setUpdatedAt(new \DateTime())
                ->save();
            return View::create('', Codes::HTTP_CREATED);
        }
        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Competition-BookParticipation",
     *  resource=true,
     *  description="Mise à jour du score d'une participation à un Concours",
     *  statusCodes = {
     *      201 = "score mis à jour",
     *   },
     *
     * )
     *
     * @Rest\Patch("/score/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $request
     * @param $id
     */
    public function patchScoreAction($id)
    {
        if ($this->checkFoundAndAccessBook($id)) {
            $participation = BookParticipationQuery::create()
                ->filterByBookId($id)
                ->filterByUser($this->getUser())
                ->findOne();
            $questionnaires = CompetitionBookQuestionnaireQuery::create()
                ->filterByBookId($id)->select('questionnaire_id')
                ->find()
                ->toArray();
            $questionnairesId = WorkshopContentQuery::create()
                ->filterByMediaId($questionnaires,\Criteria::IN)
                ->select('id')
                ->find()
                ->toArray();
            $participationsQuestionnaire = QuestionnaireParticipationQuery::create()
                ->filterByQuestionnaireId($questionnairesId, \Criteria::IN)
                ->filterByUser($this->getUser())
                ->select('score')
                ->find()
                ->toArray();
            $score = array_sum($participationsQuestionnaire);
            $participation
                ->setScore($score)
                ->keepUpdateDateUnchanged()
                ->save();

            return View::create('', Codes::HTTP_CREATED);
        }

        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *     section ="Competition-BookParticipation",
     *     resource=true,
     *     description="lister les participations d'un concours",
     *     statusCodes = {
     *      201 = "DONE",
     *     403 = "access refused"
     *   },
     *
     * )
     * @Rest\Get("/score/{id}")
     * @Rest\View(serializerGroups={"Default","book_detail"})
     * @param $id
     *
     *
     */
    public function getScoreAction($id)
    {
        if ($this->checkFoundAndAccessBook($id)) {
            $bookParticipation = BookParticipationQuery::create()
                ->filterByBookId($id)
                ->filterByUser($this->getUser())
                ->select('score')
                ->findOne();

            return $bookParticipation;
        }
        return View::create('', Codes::HTTP_NOT_FOUND);
    }

    protected function checkFoundAndAccessBook($id)
    {
        $book = BookQuery::create()
            ->findPk($id);

        if (!$book) {
            return false;
        }

        $competition = CompetitionQuery::create()
            ->filterByBook($book)
            ->findOne();

        $competitionManager = $this->get('bns.competition.competition.manager');
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return false;
        }

        return true;
    }

}
