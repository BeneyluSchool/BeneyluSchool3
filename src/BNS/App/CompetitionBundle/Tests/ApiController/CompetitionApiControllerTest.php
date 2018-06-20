<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/03/2017
 * Time: 12:45
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionGroup;
use BNS\App\CompetitionBundle\Model\CompetitionGroupQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;

class CompetitionApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param  int $groupId
     * @param  int $competitionId
     * @param  String $competitionType
     * @param   array $post
     */
    public function testPost($username, $groupId, $competitionId,$competitionType, $post)
    {

        $client = $this->getAppClient();
        $this->logIn($username);
        $client->request('POST', '/api/1.0/competitions/' .$competitionType . '/' . $groupId . '.json', $post);
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 200;
        }

        $this->assertEquals($exp, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param  int $groupId
     * @param  int $competitionId
     * @param   array $post
     */
    public function testGet($username, $groupId, $competitionId, $post)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/' . $groupId . '.json');
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testEdit($username, $groupId, $competitionId)
    {

        $client = $this->getAppClient();
        $this->logIn($username);
        $post = array("title" => "titre2", "group_ids" => [26],"status" => "PUBLISHED");
        $competition=CompetitionQuery::create()->findPk($competitionId);
        if(!$competition){
            $competition=new Competition();
            $competition->setTitle("titre")->setDescription("desc")->setGroupId($groupId)->save();
            $competitionId=$competition->getId();
        }
        $client->request('PATCH', '/api/1.0/competitions/' . $competitionId . '.json', $post);
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 200;
        }

        $this->assertEquals($exp, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testDetails($username, $groupId, $competitionId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $competition = new Competition();
        $competition->setTitle("titre")->setDescription("desc")->setGroupId($groupId)->setStatus(CompetitionPeer::STATUS_PUBLISHED)->save();
        $competitionId = $competition->getId();
        if (!CompetitionGroupQuery::create()->filterByCompetitionId($competition->getId())->filterByGroupId($groupId)->findOne()) {
            $competitionGroup = new CompetitionGroup();
            $competitionGroup->setGroupId($groupId)->setCompetitionId($competition->getId())->save();
        }
        $client->request('GET', '/api/1.0/competitions/details/' . $competitionId . '.json');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $competition->delete();
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testIndexContribution($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/contribution.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testListReadingChallenge($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/reading-challenge.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testListSimpleCompetition($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/simple-competition.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testGetLast($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/get/last.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testMoreLiked($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/get/like.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testGetParticipatedCompetitions($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/participated.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testGetNotParticipatedCompetitions($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/not-participated.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testContributedCompetitions($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/contributed.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testNotFinishedCompetitions($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/list/not-finished.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }


    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testDelete($username, $groupId, $competitionId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
            $competition=new Competition();
            $competition->setTitle("titre")->setDescription("desc")->setGroupId($groupId)->save();
            $competitionId=$competition->getId();
        $client->request('DELETE', '/api/1.0/competitions/' . $competitionId . '.json');
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $competitionId
     */
    public function testStatisticsByCompetition($username){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competitions/' . 5 . '/statistics.json');
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }


    public function usersAndGroups()
    {
        return [['enseignant', 26, 1,"CompetitionType" => "simple-competition", array("title" => "titre", "description" => "description", "status" => "PUBLISHED", "authorize_answers" => true,
            "authorize_questionnaires" => true, "questionnaires" => [], "books" => [], "user_ids" => [2,4,3], "group_ids" => [26] )],
            ['directeur', 26, 2,"CompetitionType" => "reading-challenge", array("title" => "titredir", "description" => "descriptiondir", "status" => "PUBLISHED", "authorize_answers" => true,
                "authorize_questionnaires" => false, "questionnaires" => [], "books" => [["title" => "titre livre", "author" => "auteur livre"]], "user_ids" => [2,4,3], "group_ids" => [26] )],
            ['enseignant', 26, 3,"CompetitionType" => "reading-challenge",  array("title" => "titredir", "description" => "descriptiondir", "status" => "PUBLISHED", "authorize_answers" => true,
                "authorize_questionnaires" => true, "questionnaires" => [], "books" => [] , "user_ids" => [2,4,3], "group_ids" => [26] )],
            ['eleve', 26, 7,"CompetitionType" => "reading-challenge",  array("title" => "titredir", "description" => "descriptiondir", "status" => "PUBLISHED", "authorize_answers" => true,
                "authorize_questionnaires" => false, "questionnaires" => [], "books" => [], "user_ids" => [2,4,3], "group_ids" => [26] )]
        ];
    }

    public function setUp()
    {
        CompetitionPeer::clearInstancePool();
    }

}
