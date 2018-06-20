<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 28/04/2017
 * Time: 12:42
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\CompetitionBookQuestionnaireQuery;
use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;

class CompetitionBookQuestionnaireApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     * @param int $mediaId
     */
    public function testPostQuestionnairesProposition($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $post = array("mediaId" => $mediaId);
        $client->request('POST', '/api/1.0/competition-book-questionnaire/' . $bookId . '/questionnaires.json', $post);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testListPendingQuestionnaires($username, $bookId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-questionnaire/' . $bookId . '/pending-questionnaires.json');
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
     * @param int $bookId
     * @param int $mediaId
     */
    public function testRefuseQuestionnaire($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaire = CompetitionBookQuestionnaireQuery::create()->filterByBookId($bookId)->filterByQuestionnaireId($mediaId)->findOne();
        $client->request('PATCH', '/api/1.0/competition-book-questionnaire/' . $bookId . '/refuse-questionnaire/' . $mediaId . '.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
        $questionnaire->setValidate(0)->save();
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     * @param int $mediaId
     */
    public function testAcceptQuestionnaire($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('PATCH', '/api/1.0/competition-book-questionnaire/' . $bookId . '/accept-questionnaire/' . $mediaId . '.json');
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
     * @param int $bookId
     */
    public function testListAcceptedQuestionnaires($username, $bookId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-questionnaire/accepted-questionnaires/' . $bookId . '.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }

    public function usersAndOptions()
    {
        return [['enseignant', 1, 1], ['directeur', 2, 2], ['eleve', 5, 2]];
    }
}
