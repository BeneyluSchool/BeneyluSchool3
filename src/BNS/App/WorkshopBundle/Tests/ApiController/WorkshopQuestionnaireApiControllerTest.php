<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 23/03/2017
 * Time: 12:37
 */

namespace BNS\App\WorkshopBundle\Tests\ApiController;


use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSettingQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;

class WorkshopQuestionnaireApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $id
     * @param string $type
     * @param array $post
     * @param array $answer
     */
    function testVerify($username, $id, $type, $post, $answer)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('PATCH', '/api/1.0/workshop/widget-groups/' . $id . '.json', $post);
        $widget = WorkshopWidgetQuery::create()->findPk($id);
        if (!$widget) {
            $doc = array("questionnaire" => true);
            $client->request('POST', '/api/1.0/workshop/documents.json', $doc);
            $document = WorkshopDocumentQuery::create()->findOne();
            $page = WorkshopPageQuery::create()->filterByDocumentId($document->getId())->findOne();
            $widg = array("code" => $type, "zone" => 1, "position" => 1);
            $client->request('POST', '/api/1.0/workshop/pages/' . $page->getId() . '/widget-groups.json', $widg);
            $widgetgroup = WorkshopWidgetGroupQuery::create()->findPk($id);
            $id=$widgetgroup->getId();
            $client->request('PATCH', '/api/1.0/workshop/widget-groups/' . $id . '.json', $post);
            $response = $client->getResponse();
        }
        $client->request('POST', '/api/1.0/workshop/questionnaire/' . $id . '/' . $type . '/verify.json', $answer);
        $response = $client->getResponse();
        $exp = 200;
        $this->assertEquals($exp, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $expect = true;

        $this->assertEquals($expect, $content['is_correct']);
    }


    function usersAndOptions()
    {
        return [["enseignant2", 1, 'simple', "post" => array("id" => 1, "workshop_widgets" => [array("content" => "choisis moi",
            "workshop_widget_extended_setting" => array(
                "choices" => [array("0" => "faux"), array("1" => "vrai"), array("2" => "faux")]
            , "correct_answers" => 2))]), array("data" => 2, "show_solution" => true)],
            ["directeur", 1, 'simple', "post" => array("id" => 1, "workshop_widgets" => [array("content" => "choisis moi",
                "workshop_widget_extended_setting" => array(
                    "choices" => [array("0" => "faux"), array("1" => "vrai"), array("2" => "faux")]
                , "correct_answers" => 2))]), array("data" => 2, "show_solution" => true)]]
            ;
    }
}
