<?php
namespace BNS\App\ProfileBundle\Tests\Controller;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use FOS\RestBundle\Util\Codes;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackControllerTest extends AppWebTestCase
{
    public function assistanceUsers()
    {
        return [
            ['enseignant', false],
            ['elevePAR', false],
            ['enseignant2', true],
        ];
    }


    /**
     * @dataProvider assistanceUsers
     */
    public function testAssistanceTab($username, $hasAssistance)
    {
        $client = $this->getAppClient();
        $this->logIn($username);

        $crawler = $client->request('GET', '/profil/gestion/');
        $response = $client->getResponse();

        $this->assertEquals(Codes::HTTP_OK, $response->getStatusCode());

        $textToFind = $client->getContainer()->get('translator')->trans('LINK_ASSISTANCE', [], 'PROFILE');
        $nodeHtml = $crawler->filter('md-sidenav')->html();
        if ($hasAssistance) {
            $this->assertContains($textToFind, $nodeHtml, 'user has assistance the tab should be present');
        } else {
            $this->assertNotContains($textToFind, $nodeHtml, 'user does not have assistance the tab should not be present');
        }
    }

    /**
     * @dataProvider assistanceUsers
     */
    public function testAssistanceStatus($username, $hasAssistance)
    {
        $client = $this->getAppClient();
        $user = $this->logIn($username);
        $profile = $user->getProfile();

        $client->request('POST', '/profil/gestion/assistance/switch');
        $response = $client->getResponse();

        if ($hasAssistance) {
            $this->assertEquals(Codes::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            $this->assertEquals($profile->getAssistanceEnabled(), $content->moderate);
        } else {
            $this->assertEquals(Codes::HTTP_FORBIDDEN, $response->getStatusCode());
        }
    }

    /**
     * @dataProvider assistanceUsers
     */
    public function testAssistanceSwitchStatus($username, $hasAssistance)
    {
        $client = $this->getAppClient();
        $client->restart();
        $this->logIn($username);

        $client->request('POST', '/profil/gestion/assistance/switch', [], [], [], json_encode(['state' => true]));
        $response = $client->getResponse();

        if ($hasAssistance) {
            $this->assertEquals(Codes::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            $this->assertFalse($content->moderate);
        } else {
            $this->assertEquals(Codes::HTTP_FORBIDDEN, $response->getStatusCode());
        }

        $client->request('POST', '/profil/gestion/assistance/switch', [], [], [], json_encode(['state' => false]));
        $response = $client->getResponse();

        if ($hasAssistance) {
            $this->assertEquals(Codes::HTTP_OK, $response->getStatusCode());
            $content = json_decode($response->getContent());
            $this->assertTrue(true, $content->moderate);
        } else {
            $this->assertEquals(Codes::HTTP_FORBIDDEN, $response->getStatusCode());
        }
    }
}
