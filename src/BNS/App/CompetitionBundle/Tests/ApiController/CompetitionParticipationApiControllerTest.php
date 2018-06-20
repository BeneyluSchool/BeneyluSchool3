<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 24/04/2017
 * Time: 17:10
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CoreBundle\Test\AppWebTestCase;

class CompetitionParticipationApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $competitionId
     */
    public function testPost($username, $competitionId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('POST', '/api/1.0/competition-participation/'. $competitionId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $competitionId
     */
    public function testGetScore($username, $competitionId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-participation/score/'. $competitionId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    public function usersAndOptions()
    {
        return[["enseignant", 4 ],["eleve", 5]];

    }
}
