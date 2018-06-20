<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 02/05/2017
 * Time: 10:10
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CoreBundle\Test\AppWebTestCase;

class BookParticipationApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testPost($username, $bookId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('POST', '/api/1.0/competition-book-participation/' . $bookId . '.json');
        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testGetScore($username, $bookId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-participation/score/'. $bookId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testIndex($username, $bookId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-participation/'. $bookId .'.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    public function usersAndOptions()
    {
        return [["enseignant", 1], ["eleve", 2]];

    }
}
