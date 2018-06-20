<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 23/03/2017
 * Time: 17:07
 */

namespace BNS\App\WorkshopBundle\Tests\ApiController;


use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;

class WorkshopPageApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $userId
     * @param int $pageId
     * @param array $post
     */
    function testPostWidgetGroup($username, $userId, $pageId, $post)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $page = WorkshopPageQuery::create()->findPk($pageId);
        if (!$page) {
            $doc= array("questionnaire"=>true);
            $client->request('POST', '/api/1.0/workshop/documents.json',$doc);
            $document=WorkshopDocumentQuery::create()->findOne();
            $page = new WorkshopPage();
            $page->setDocumentId($document->getId())->save();
            $pageId = $page->getId();
        }
        $client->request('POST', '/api/1.0/workshop/pages/' . $pageId . '/widget-groups.json', $post);
        $response = $client->getResponse();
        $this->assertEquals(201, $response->getStatusCode());

    }

    public function usersAndOptions()
    {
        return [["enseignant2",2, 13, "post" => array("code" => "simple", "zone" => 1, "position" => 1)],
            ["enseignant2",2, 13, "post" => array("code" => "multiple", "zone" => 1, "position" => 1)]
        ];
    }
}
