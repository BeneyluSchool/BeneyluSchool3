<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 24/03/2017
 * Time: 16:37
 */

namespace BNS\App\WorkshopBundle\Tests\ApiController;


use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;

class WorkshopWidgetGroupApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param String $username
     * @param int $id
     * @param array $post
     */
    function testPatch($username, $id, $post)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $widgetGroup = WorkshopWidgetGroupQuery::create()->findPk($id);
        if (!$widgetGroup) {
            $doc= array("questionnaire"=>true);
            $client->request('POST', '/api/1.0/workshop/documents.json',$doc);
            $document=WorkshopDocumentQuery::create()->findOne();
            $page = WorkshopPageQuery::create()->findOne();
            $widgetGroup = new WorkshopWidgetGroup();
            $widgetGroup->setPageId($page->getId())->save();
            $id = $widgetGroup->getId();
        }
        $client->request('PATCH', '/api/1.0/workshop/widget-groups/' . $id . '.json', $post);
        $response = $client->getResponse();
        if('directeur' === $username){
            $exp = 403;
        } else {
            $exp = 204;
        }
        $this->assertEquals($exp, $response->getStatusCode());

    }

    function usersAndOptions()
    {
        return [["enseignant2", 1, "post" => array("id" => 1, "workshop_widgets" => [array("content" => "choisis moi",
            "workshop_widget_extended_setting" => array(
                "choices" => [array("0" => "faux"), array("1" => "vrai"), array("2" => "faux")]
            , "correct_answers" => 2))])],
            ["enseignant2", 2, "post" => array("id" => 2, "workshop_widgets" => [array("content" => "choisis 2 et 3",
                "workshop_widget_extended_setting" => array(
                    "choices" => [array("0" => "faux"), array("1" => "vrai"), array("2" => "vrai")]
                , "correct_answers" => [2, 3]))])],
            ["directeur", 1, "post" => array("id" => 3, "workshop_widgets" => [array("content" => "",
                "workshop_widget_extended_setting" => array(
                    "choices" => '<p>ceci <span data-bns-gap-guid=\"85852c77-3ea6-4836-b838-7e37d73b66ab\">est</span> un <span data-bns-gap-guid=\"1206eb10-cd6f-4d5f-b611-0da94e79aeae\">texte</span> à trou.</p>'
                , "correct_answers" => [array("guid" => "85852c77-3ea6-4836-b838-7e37d73b66ab", "label" => "est"), array("guid" => "1206eb10-cd6f-4d5f-b611-0da94e79aeae", "label" => "texte")]))])]
        ];
    }
}
