<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 06/04/2017
 * Time: 10:59
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\StoreBundle\Client\Message\Request;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\Validator\Constraints\DateTime;

class CompetitionParticipationApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition-Competition-Participation",
     *     resource=true,
     *     description="lister les participations d'un concours",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $id
     *
     *
     */
    public function indexAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $participations = CompetitionParticipationQuery::create()
            ->filterByCompetitionId($id)
            ->lastUpdatedFirst()
            ->find();

        return $participations;
    }

    /**
     * @ApiDoc(
     *  section="Competition-Competition-Participation",
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
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);

        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $participation = new CompetitionParticipation();
        $participation->setUserId($this->getUser()->getId())
            ->setCompetitionId($id)
            ->setScore(0)
            ->save();

        return View::create('', Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section="Competition-Competition-Participation",
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
     * @param $id
     */
    public function patchAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competitionParticipation = CompetitionParticipationQuery::create()->findPk($id);

        if (!$competitionManager->canViewCompetition($competitionParticipation->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        if (!$competitionParticipation) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }

        $competitionParticipation->setUpdatedAt(new \DateTime())->save();

        return View::create('', Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Competition-Competition-Participation",
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
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);

        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $participation = CompetitionParticipationQuery::create()->filterByCompetitionId($id)->filterByUserId($this->getUser()->getId())->findOne();
        if (!$participation) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $questionnaires = CompetitionQuestionnaireQuery::create()->filterByCompetitionId($id)->select('questionnaire_id')->find();
        $participationsQuestionnaire = QuestionnaireParticipationQuery::create()
            ->filterByQuestionnaireId($questionnaires, \Criteria::IN)
            ->filterByUserId($this->getUser()->getId())
            ->select('score')
            ->find()
            ->toArray();
        $score = array_sum($participationsQuestionnaire);
        $participation->setScore($score)->keepUpdateDateUnchanged()->save();

    }

    /**
     * @ApiDoc(
     *     section ="Competition-Competition-Participation",
     *     resource=true,
     *     description="lister les participations d'un concours",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Get("/score/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param $id
     *
     *
     */
    public function getScoreAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);
        if (!$competitionManager->canViewCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $participation = CompetitionParticipationQuery::create()
            ->filterByCompetitionId($id)
            ->filterByUserId($this->getUser()->getId())
            ->select('score')
            ->findOne();
        if (null == $participation) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        return $participation;
    }
}
