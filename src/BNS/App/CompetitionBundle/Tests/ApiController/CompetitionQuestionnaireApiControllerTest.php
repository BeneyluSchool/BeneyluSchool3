<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 28/04/2017
 * Time: 13:19
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\CompetitionQuestionnaireQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

class CompetitionQuestionnaireApiControllerTest extends AppWebTestCase
{

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $competitionId
     * @param int $mediaId
     */
    public function testPostQuestionnaireProposition($username, $competitionId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('POST', '/api/1.0/workshop/documents.json');
        $media = MediaQuery::create()->findPk($mediaId);
        $post = array("mediaId" => $media->getId());
        $client->request('POST', '/api/1.0/competition-questionnaire/' . $competitionId . '/questionnaires.json', $post);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testListPendingQuestionnaires($username, $competitionId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-questionnaire/' . $competitionId . '/pending-questionnaires.json');
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
     * @param int $competitionId
     * @param int $mediaId
     */
    public function testRefuseQuestionnaire($username, $competitionId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $questionnaire = CompetitionQuestionnaireQuery::create()->filterByCompetitionId($competitionId)->filterByQuestionnaireId($mediaId)->findOne();
        $client->request('PATCH', '/api/1.0/competition-questionnaire/' . $competitionId . '/refuse-questionnaire/' . $mediaId . '.json');
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
     * @param int $competitionId
     * @param int $mediaId
     */
    public function testAcceptQuestionnaire($username, $competitionId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('PATCH', '/api/1.0/competition-questionnaire/' . $competitionId . '/accept-questionnaire/' . $mediaId . '.json');
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
     * @param int $competitionId
     */
    public function testListAcceptedQuestionnaires($username, $competitionId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-questionnaire/accepted-questionnaires/' . $competitionId . '.json');
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
        return [['enseignant', 4, 1], ['directeur', 5, 2], ['eleve', 6, 2]];
    }

}
