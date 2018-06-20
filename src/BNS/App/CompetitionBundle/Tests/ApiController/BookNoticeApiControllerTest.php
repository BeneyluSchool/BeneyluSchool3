<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 28/04/2017
 * Time: 11:43
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\BookNoticeQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;

class BookNoticeApiControllerTest extends AppWebTestCase
{
    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     * @param int $mediaId
     */
    public function testPostNoticeProposition($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('POST', '/api/1.0/workshop/documents.json');
        $media = MediaQuery::create()->findPk($mediaId);
        $post = array("mediaId" => $media->getId());
        $client->request('POST', '/api/1.0/competition-book-notice/' . $bookId . '/notice.json', $post);
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     */
    public function testListPendingNotices($username, $bookId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-notice/' . $bookId . '/pending-notices.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     * @param int $mediaId
     */
    public function testRefuseNotice($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-notice/' . $bookId . '/refuse-notice/' . $mediaId . '.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
        $notice = BookNoticeQuery::create()->filterByBookId($bookId)->filterByNoticeId($mediaId)->findOne();
        $notice->setValidate(0)->save();
    }

    /**
     * @dataProvider usersAndOptions
     *
     * @param string $username
     * @param int $bookId
     * @param int $mediaId
     */
    public function testAcceptNotice($username, $bookId, $mediaId){
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/competition-book-notice/' . $bookId . '/accept-notice/' . $mediaId . '.json');
        $response = $client->getResponse();
        if( "eleve" == $username){
            $exp = 403;
        }else{
            $exp = 200;
        }
        $this->assertEquals($exp, $response->getStatusCode());
    }



    public function usersAndOptions()
    {
        return [['enseignant', 1, 1], ['directeur', 2, 2], ['eleve', 3, 2]];
    }

}
