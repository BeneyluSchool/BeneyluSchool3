<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 27/04/2017
 * Time: 12:27
 */

namespace BNS\App\CompetitionBundle\ApiController;


use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\GroupQuery;
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

class CompetitionQuestionnaireApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
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
     * @param $id
     *
     * @return array
     */
    public function listPendingQuestionnairesAction($id)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $competition = CompetitionQuery::create()
            ->findPk($id);
        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $pendingQuestionnaires = CompetitionQuestionnaireQuery::create()
            ->filterByCompetition($competition)
            ->filterByValidate(CompetitionQuestionnaire::VALIDATE_PENDING)
            ->find();
        foreach ($pendingQuestionnaires as $pendingQuestionnaire) {
            $media = $pendingQuestionnaire->getQuestionnaire();
            $media->questionsCount = $media->getWorkshopContent()->getWorkshopDocument()->getWorkshopDocumentQuestionsCount();
        }

        return $pendingQuestionnaires;
    }


    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
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
        $competition = CompetitionQuery::create()
            ->findPk($id);
        if (!$competition) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($competition, $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $acceptedQuestionnaires = CompetitionQuestionnaireQuery::create()
            ->filterByCompetitionId($id)
            ->filterByValidate(CompetitionQuestionnaire::VALIDATE_VALIDATED)
            ->find();

        return $acceptedQuestionnaires;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
     *     resource=true,
     *     description="Refuse un questionnaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Patch("/{id}/refuse-questionnaire/{questionnaireId}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param $id
     * @param $questionnaireId
     *
     */
    public function refuseQuestionnaireAction($id, $questionnaireId)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $questionnaire = CompetitionQuestionnaireQuery::create()
            ->filterByQuestionnaireId($questionnaireId)
            ->filterByValidate(CompetitionQuestionnaire::VALIDATE_PENDING)
            ->filterByCompetitionId($id)
            ->findOne();
        if (!$questionnaire) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($questionnaire->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }
        $questionnaire->setValidate(CompetitionQuestionnaire::VALIDATE_REFUSED)->save();
        $this->get('bns.competition.notification.manager')->notificateRefusedQuestionnaireCompetition($questionnaire);
        return $questionnaire;
    }


    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
     *     resource=true,
     *     description="Accepte un questionnaire",
     *     statusCodes = {
     *      200 = "DONE",
     *     403 = "access refused"
     *   },
     *
     *     )
     * @Rest\Patch("/{id}/accept-questionnaire/{questionnaireId}")
     * @Rest\View(serializerGroups={"Default","competition_detail","media_basic","user_avatar"})
     * @param $questionnaireId
     * @param $id
     *
     */
    public function acceptQuestionnaireAction($id, $questionnaireId)
    {
        $competitionManager = $this->get('bns.competition.competition.manager');
        $questionnaire = CompetitionQuestionnaireQuery::create()->filterByValidate(0)->filterByCompetitionId($id)->filterByQuestionnaireId($questionnaireId)->findOne();
        if (!$questionnaire) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        if (!$competitionManager->canManageCompetition($questionnaire->getCompetition(), $this->getUser())) {
            return View::create('', Codes::HTTP_FORBIDDEN);
        }

        $media = MediaQuery::create()->findPk($questionnaireId);
        if (!$media){
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $copy = $media->copy();
        $copy->setStatusDeletion(MediaManager::STATUS_QUESTIONNAIRE_COMPETITION);
        $copy->save();
        $content = WorkshopContentQuery::create()
            ->filterByMediaId($questionnaireId)
            ->findOne();

        $manager = $this->get('bns.workshop.widget_group.manager');
        $contCopy = $manager->duplicateContent($content);
        $contCopy->setMediaId($copy->getId());
        $contCopy->save();


        $groupsContributors = WorkshopContentGroupContributorQuery::create()
            ->filterByContentId($content->getId())
            ->select('group_id')
            ->find()->toArray();
        $usersContributorsIds = WorkshopContentContributorQuery::create()
            ->filterByContentId($content->getId())
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
                $participation = CompetitionParticipationQuery::create()
                    ->filterByCompetitionId($questionnaire->getCompetitionId())
                    ->filterByUserId($userContributor)
                    ->findOne();
                if (!$participation) {
                    $participation = new CompetitionParticipation();
                    $participation->setCompetitionId($questionnaire->getCompetitionId())->setUserId($userContributor)->save();
                }
                $participation->setScore($participation->getScore() + 1)->save();
            }
        }

        $questionnaire->setValidate(CompetitionQuestionnaire::VALIDATE_VALIDATED)->setQuestionnaireId($copy->getId());
        CompetitionQuestionnaireQuery::create()->filterByCompetitionId($id)->filterByQuestionnaireId($questionnaireId)
            ->filterByValidate(CompetitionQuestionnaire::VALIDATE_PENDING)->update(array('Validate' => CompetitionQuestionnaire::VALIDATE_VALIDATED, 'QuestionnaireId' => $copy->getId()));

        $this->get('bns.competition.notification.manager')->notificateAcceptedQuestionnaireCompetition($questionnaire);
        if ($questionnaire->getCompetition()->getAuthorizeAnswers()) {
            $this->get('bns.competition.notification.manager')->notificateNewQuestionnaireCompetition($questionnaire);
        }

        return $questionnaire;
    }


    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
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
     * @param $id
     * @param $request
     */
    public function postQuestionnairesPropositionAction(Request $request, $id)
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
        $questionnaireProposed = CompetitionQuestionnaireQuery::create()
            ->filterByCompetitionId($id)
            ->filterByQuestionnaireId($request->get('mediaId'))
            ->findOne();
        if ($questionnaireProposed && $questionnaireProposed->getValidate() === CompetitionQuestionnaire::VALIDATE_PENDING) {
            return View::create('', Codes::HTTP_BAD_REQUEST);
        }
        if ($questionnaireProposed && $questionnaireProposed->getValidate() === CompetitionQuestionnaire::VALIDATE_REFUSED) {
            $questionnaireProposed->setValidate(CompetitionQuestionnaire::VALIDATE_PENDING)->save();
            return $questionnaireProposed;
        }
        $questionnaire = new CompetitionQuestionnaire();
        $questionnaire->setCompetitionId($id)
            ->setQuestionnaireId($request->get('mediaId'))
            ->setValidate(CompetitionQuestionnaire::VALIDATE_PENDING)
            ->setUserId($this->getUser()->getId())
            ->setProposer($this->getUser()->getFullName())
            ->save();

        $this->get('bns.competition.notification.manager')->notificateQuestionnairePropositionCompetition($questionnaire);
        return $questionnaire;
    }

    /**
     * @ApiDoc(
     *     section ="Competition - CompetitionQuestionnaire",
     *     resource=true,
     *     description="Liste les questionnaires en attente de validation",
     *     statusCodes = {
     *      201 = "DONE",
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
    public function myListPendingQuestionnairesAction(ParamFetcherInterface $paramFetcher, $id)
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

        $myPendingQuestionnaires = CompetitionQuestionnaireQuery::create()
            ->filterByCompetition($competition)
            ->filterByUserId($this->getUser()->getId())
            ->filterByValidate(CompetitionQuestionnaire::VALIDATE_PENDING)
            ->orderByCreatedAt('DESC')
            ->find();

        return $myPendingQuestionnaires;
    }

}
