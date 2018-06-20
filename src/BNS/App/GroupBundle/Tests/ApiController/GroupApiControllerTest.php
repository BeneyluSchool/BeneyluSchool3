<?php

namespace BNS\App\GroupBundle\Tests\ApiController;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use FOS\RestBundle\Util\Codes;

class GroupApiControllerTest extends AppWebTestCase
{
    public function users()
    {
        return [
            ['enseignant'],
            ['eleve'],
            ['elevePAR'],
            ['administrateur'],
        ];
    }

    public function userWithRights()
    {
        return [
            ['enseignant', 'CLASSROOM_ACCESS', true],
            ['eleve', 'CLASSROOM_ACCESS', true],
            ['elevePAR', 'CLASSROOM_ACCESS', true],
            ['enseignant', 'CLASSROOM_ACCESS_BACK', true],
            ['eleve', 'CLASSROOM_ACCESS_BACK', false],
            ['elevePAR', 'CLASSROOM_ACCESS_BACK', false],
            ['administrateur', 'ADMIN_ACCESS_STRONG', true],
        ];
    }


    /**
     * @dataProvider users
     */
    public function testGetGroupsCurrent($username)
    {
        $client = $this->getAppClient();
        $this->logIn($username, true);

        $client->request('GET', '/api/1.0/groups.json');
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($content));
        foreach ($content as $group) {
            if (isset($group['page'])) {
                // fake group for premium push
                $this->assertArrayHasKey('name', $group['page']);
                $this->assertArrayHasKey('vars', $group['page']);
            } else {
                $this->assertArrayHasKey('id', $group);
            }
            $this->assertArrayHasKey('label', $group);
            $this->assertArrayHasKey('type', $group);
        }
    }

    /**
     * @dataProvider userWithRights
     */
    public function testGetGroupsCurrentWithRight($username, $right, $hasGroup)
    {
        $client = $this->getAppClient();
        $this->logIn($username, true);

        $client->request('GET', '/api/1.0/groups.json', ['right' => $right]);
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        if ($hasGroup) {
            $this->assertGreaterThan(0, count($content));
        } else {
            $this->assertEquals(0, count($content));
        }
    }


    /**
     * @dataProvider users
     */
    public function testGetGroupCurrent($username)
    {
        $client = $this->getAppClient();
        $this->logIn($username, true);

        $client->request('GET', '/api/1.0/groups/current.json');
        $response = $client->getResponse();
        $group = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('id', $group);
        $this->assertArrayHasKey('label', $group);
        $this->assertArrayHasKey('type', $group);
    }

    public function testPatchGroupCurrent()
    {
        $client = $this->getAppClient();
        $client->restart();
        $client->request('PATCH', '/api/1.0/groups/current.json');
        $response = $client->getResponse();
        $this->assertEquals(Codes::HTTP_UNAUTHORIZED, $response->getStatusCode(), 'unconnected user should receive a 401 error');

        $this->logIn('enseignant');

        $client->request('PATCH', '/api/1.0/groups/current.json');
        $response = $client->getResponse();
        $this->assertEquals(Codes::HTTP_BAD_REQUEST, $response->getStatusCode(), 'no group id sent should receive a 400 error');

        $client->request('PATCH', '/api/1.0/groups/current.json', ['id' => '99999999999999999999']);
        $response = $client->getResponse();
        $this->assertEquals(Codes::HTTP_NOT_FOUND, $response->getStatusCode(), 'invalid group id should receive a 404 error');

        $client->request('GET', '/api/1.0/groups.json');
        $response = $client->getResponse();
        $groups = json_decode($response->getContent(), true);

        $group =  reset($groups);
        $client->request('PATCH', '/api/1.0/groups/current.json', ['id' => $group['id']]);
        $response = $client->getResponse();
        $this->assertEquals(Codes::HTTP_OK, $response->getStatusCode(), 'valid group id should receive a 200');
    }


    /**
     * @dataProvider users
     */
    public function testGetGroupApplications($username)
    {
        $client = $this->getAppClient();
        $this->logIn($username, true);

        $client->request('GET', '/api/1.0/groups.json');
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($content));

        $group = reset($content);

        $client->request('GET', sprintf('/api/1.0/groups/%s/applications.json', $group['id']));
        $response = $client->getResponse();
        $apps = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($apps));

        $keys = [
            'id',
            'unique_name',
            'is_open',
            'label',
            'rank',
            'has_access_back',
            'has_access_front',
        ];
        foreach ($apps as $app) {
            foreach ($keys as $key)
            $this->assertArrayHasKey($key, $app);
        }
    }
}
