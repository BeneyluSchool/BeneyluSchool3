<?php

namespace BNS\App\UserBundle\Tests\ApiController;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;

class UserApiControllerTest extends AppWebTestCase
{
    public function users()
    {
        return [
            ['enseignant', true],
            ['eleve', false],
            ['elevePAR', true],
            ['administrateur', true]
        ];
    }


    public function testGetMeNotConnected()
    {
        $client = $this->getAppClient();

        $client->request('GET', '/api/1.0/users/me.json');
        $response = $client->getResponse();

        $this->assertEquals(401, $response->getStatusCode());
    }

    /**
     * @dataProvider users
     */
    public function testGetMeConnected($username, $isAdult)
    {
        $client = $this->getAppClient();

        $this->logIn($username);

        $client->request('GET', '/api/1.0/users/me.json');
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('id', $content);
        $this->assertArrayHasKey('avatar_url', $content);
        $this->assertArrayHasKey('is_adult', $content);
        $this->assertEquals($isAdult, $content['is_adult']);
        $this->assertArrayHasKey('full_name', $content);
    }

    public function testApi()
    {
        $client = $this->getAppClient();

        $container = $client->getContainer();

        $user = UserQuery::create()->filterByLogin('enseignant')->findOne();

        $user->setLastName('bob2');

        $container->get('bns.user_manager')->updateUser($user);

        $this->assertEquals('Bob2', $user->getLastName());

        $userData = $container->get('bns.api')->send('user_read', [
            'route' => ['username' => 'enseignant']
        ], false);

        $this->assertEquals('Bob2', $userData['last_name']);
    }
}
