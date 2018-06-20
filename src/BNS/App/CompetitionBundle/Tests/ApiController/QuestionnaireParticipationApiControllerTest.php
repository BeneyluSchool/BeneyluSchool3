<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 25/04/2017
 * Time: 15:05
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\CompetitionParticipation;
use BNS\App\CompetitionBundle\Model\CompetitionParticipationQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaire;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipation;
use BNS\App\CompetitionBundle\Model\QuestionnaireParticipationQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;

class QuestionnaireParticipationApiControllerTest extends AppWebTestCase
{

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $questionnaireId
     * @param int $userId
     * @param int $participationId
     */
    public function testPatchPage($username, $questionnaireId, $userId, $participationId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaire = MediaQuery::create()->findPk($questionnaireId);
        if (!$questionnaire){
            $questionnaire = new Media();
            $questionnaire->setUserId(2)->setLabel("questionnaire test")->save();
            $content = new WorkshopContent();
            $content->setAuthorId(2)->setMediaId($questionnaire->getId())->save();
            $document = new WorkshopDocument();
            $document->setId($content->getId())->setDocumentType(2)->save();
            $page = new WorkshopPage();
            $page->setWorkshopDocument($document)->setPosition(1)->save();
            $questionnaireId = $questionnaire->getId();
        }
        $participation = QuestionnaireParticipationQuery::create()->filterByUserId($userId)->filterByQuestionnaireId($questionnaireId)->findOne();
        if (!$participation) {
            $participation = new QuestionnaireParticipation();
            $participation->setUserId($userId)->setQuestionnaireId($questionnaireId)->setPage(1)->setScore(0)->save();
        }
        $client->request('PATCH', '/api/1.0/questionnaire-participation/page/'. $questionnaireId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $participation = QuestionnaireParticipationQuery::create()->filterByUserId($userId)->filterByQuestionnaireId($questionnaireId)->findOne();
        $participation->setScore(4)->save();
        $this->assertEquals(2, $participation->getPage());
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $questionnaireId
     * @param int $userId
     * @param int $participationId
     */
    public function testPatchNew($username, $questionnaireId, $userId, $participationId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaire = MediaQuery::create()->findPk($questionnaireId);
        if (!$questionnaire){
            $questionnaire = new Media();
            $questionnaire->setUserId(2)->setLabel("questionnaire test")->save();
            $content = new WorkshopContent();
            $content->setAuthorId(2)->setMediaId($questionnaire->getId())->save();
            $document = new WorkshopDocument();
            $document->setId($content->getId())->setDocumentType(2)->save();
            $page = new WorkshopPage();
            $page->setWorkshopDocument($document)->setPosition(1)->save();
            $questionnaireId = $questionnaire->getId();
        }
            $competitionQuestionnaire = CompetitionQuestionnaireQuery::create()->filterByQuestionnaireId($questionnaireId)->findOne();
            $competitionQuestionnaire->setAllowAttempts(true)->setAttemptsNumber(4)->save();
        $participation = QuestionnaireParticipationQuery::create()->filterByUserId($userId)->filterByQuestionnaireId($questionnaireId)->findOne();
        if (!$participation) {
            $participation = new QuestionnaireParticipation();
            $participation->setUserId($userId)->setQuestionnaireId($questionnaireId)->setPage(1)->setScore(0)->save();
        }
        $client->request('PATCH', '/api/1.0/questionnaire-participation/new/'. $questionnaireId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        $participation = QuestionnaireParticipationQuery::create()->filterByUserId($userId)->filterByQuestionnaireId($questionnaireId)->findOne();

            $this->assertEquals(1, $participation->getPage());

    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $questionnaireId
     *
     */
    public function testIndex($username, $questionnaireId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaireParticipation = CompetitionQuestionnaireQuery::create()->filterByQuestionnaireId($questionnaireId)->findOne();
        if (!$questionnaireParticipation) {
           $questionnaireParticipation = new CompetitionQuestionnaire();
           $questionnaireParticipation->setQuestionnaireId($questionnaireId)->setCompetitionId(1)->save();
        }
        $client->request('GET', '/api/1.0/questionnaire-participation/'. $questionnaireId .'.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $questionnaireId
     * @param int $userId
     */
    public function testPatchLike($username, $questionnaireId, $userId, $participationId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaire = MediaQuery::create()->findPk($questionnaireId);
        if (!$questionnaire){
            $questionnaire = new Media();
            $questionnaire->setUserId(2)->setLabel("questionnaire test")->save();
            $content = new WorkshopContent();
            $content->setAuthorId(2)->setMediaId($questionnaire->getId())->save();
            $document = new WorkshopDocument();
            $document->setId($content->getId())->setDocumentType(2)->save();
            $page = new WorkshopPage();
            $page->setWorkshopDocument($document)->setPosition(1)->save();
            $questionnaireId = $questionnaire->getId();
        }
        $participation = QuestionnaireParticipationQuery::create()->filterByUserId($userId)->filterByQuestionnaireId($questionnaireId)->findOne();
        if (!$participation) {
            $participation = new QuestionnaireParticipation();
            $participation->setUserId($userId)->setQuestionnaireId($questionnaireId)->setPage(1)->setScore(0)->save();
        }
        $client->request('PATCH', '/api/1.0/questionnaire-participation/like/'. $questionnaireId .'.json');
        $response = $client->getResponse();
        if ("eleve" == $username) {
        $this->assertEquals(204, $response->getStatusCode());
        $participation = CompetitionParticipationQuery::create()->filterByUserId($userId)->filterByCompetitionId($questionnaire->getCompetition()->getId())->findOne();
        $this->assertEquals(true, $participation->getLike());
        $participation->delete();
        } else {
            $this->assertEquals(404, $response->getStatusCode());
        }

    }

    public function usersAndOptions()
    {
        return[["enseignant", 10, 2, 1 ],["eleve", 10, 4, 2]];

    }
}
