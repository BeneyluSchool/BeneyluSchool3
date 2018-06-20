<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 03/03/2017
 * Time: 14:50
 */

namespace BNS\App\CompetitionBundle\Tests\ApiController;


use BNS\App\CompetitionBundle\Model\Book;
use BNS\App\CompetitionBundle\Model\BookQuery;
use BNS\App\CompetitionBundle\Model\Competition;
use BNS\App\CompetitionBundle\Model\CompetitionGroup;
use BNS\App\CompetitionBundle\Model\CompetitionGroupQuery;
use BNS\App\CompetitionBundle\Model\CompetitionPeer;
use BNS\App\CompetitionBundle\Model\ReadingChallenge;
use BNS\App\CompetitionBundle\Tests\Manager\CompetitionManager;
use BNS\App\CoreBundle\Test\AppWebTestCase;

class BookApiControllerTest extends AppWebTestCase
{


    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     */
    public function testGet($username, $groupId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $client->request('GET', '/api/1.0/books/' . $groupId . '.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
    }


    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $bookId
     */
    public function testEdit($username, $groupId, $bookId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $competition = new ReadingChallenge();
        $competition->setTitle("Titre test")->setDescription("Description Test")->setGroupId($groupId)->setStatus(CompetitionPeer::STATUS_PUBLISHED)->save();
        $competitionGroups = new CompetitionGroup();
        $competitionGroups->setCompetitionId($competition->getId())->setGroupId($groupId)->save();
        $book = new Book();
        $book->setCompetition($competition)->setTitle("title")->setAuthor("author")->setGroupId(26)->setUserId(2)->save();
        $bookId = $book->getId();
        $post = array("title" => "Anna Karenine");
        $client->request('PATCH', '/api/1.0/books/edit/' . $bookId . '.json', $post);
        $response = $client->getResponse();
        if ('eleve' == $username) {
            $exp = 403;
        } else {
            $exp = 204;
        }
        $this->assertEquals($exp, $response->getStatusCode());

    }

    /**
     * @dataProvider usersAndGroups
     *
     * @param string $username
     * @param int $groupId
     * @param int $bookId
     */
    public function testDetails($username, $groupId, $bookId)
    {
        $client = $this->getAppClient();
        $this->logIn($username, false);
        $book = BookQuery::create()->findPk($bookId);
        if(!$book){
            $competition = new ReadingChallenge();
            $competition->setTitle("Titre test")->setDescription("Description Test")->setGroupId(26)->setStatus(CompetitionPeer::STATUS_PUBLISHED)->save();
            $book = new Book();
            $book->setCompetitionId($competition->getId())->setTitle("title")->setAuthor("author")->setGroupId(26)->setUserId(2)->save();
            $bookId = $book->getId();
            $competitionGroups = new CompetitionGroup();
            $competitionGroups->setCompetitionId($competition->getId())->setGroupId($groupId)->save();
        }
        $client->request('GET', '/api/1.0/books/details/' . $bookId . '.json');
        $response = $client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());

    }

    public function usersAndGroups()
    {
        return [['enseignant', 26, 1], ['directeur', 26, 2], ['eleve', 26, 999]];
    }
}
