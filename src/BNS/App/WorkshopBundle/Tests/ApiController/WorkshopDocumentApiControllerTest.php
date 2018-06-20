<?php

namespace BNS\App\WorkshopBundle\Tests\ApiController;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use Symfony\Component\HttpFoundation\Response;

class WorkshopDocumentApiControllerTest extends AppWebTestCase
{

    public function testPost()
    {
        $username = 'enseignant2';
        $client = $this->getAppClient();
        $this->logIn($username, false);

        $this->ensureAppIsOpen('WORKSHOP', 'enseignant2');
        $post = array("questionnaire" => true);
        $client->request('POST', '/api/1.0/workshop/documents.json', $post);
        $response = $client->getResponse();

        $this->assertEquals(201, $response->getStatusCode());

        $user = UserQuery::create()->filterByLogin($username)->findOne();

        $destination = MediaFolderUserQuery::create()
            ->filterByUserId($user->getId())
            ->filterByStatusDeletion(1)
            ->findOneByIsWorkshop(true);

        $this->assertNotNull($destination);
    }

    public function testPostDeniedParent()
    {
        $this->ensureAppIsOpen('WORKSHOP', 'enseignant2');

        $client = $this->getAppClient();
        $client->restart();

        $username = 'eleve2PAR';
        $this->logIn($username, false);


        $post = array("questionnaire" => true);
        $client->request('POST', '/api/1.0/workshop/documents.json', $post);
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $user = UserQuery::create()->filterByLogin($username)->findOne();

        $destination = MediaFolderUserQuery::create()
            ->filterByUserId($user->getId())
            ->filterByStatusDeletion(1)
            ->findOneByIsWorkshop(true);

        $this->assertNull($destination);
    }

    public function testList()
    {
        $username = 'enseignant2';
        $client = $this->getAppClient();
        $this->logIn($username, false);

        $client->request('GET', '/api/1.0/workshop/documents.json');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());

    }

    public function testListParents()
    {
        $username = 'eleve2PAR';
        $client = $this->getAppClient();
        $this->logIn($username, false);

        $client->request('GET', '/api/1.0/workshop/documents.json');
        $response = $client->getResponse();

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

}
