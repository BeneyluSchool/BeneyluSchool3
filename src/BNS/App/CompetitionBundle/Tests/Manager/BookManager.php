<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 27/04/2017
 * Time: 12:10
 */

namespace BNS\App\CompetitionBundle\Tests\Manager;


use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;

class BookManager extends AppWebTestCase
{
    public function testBookManager(){
        $manager = $this->getManager();
        $client = $this->getAppClient();
        $this->logIn("enseignant", false);
        $post = array("title" => "testLivre",
            "author" => "testAuteur",
            "authorize_questionnaires" => true,
            "authorize_notices" => true,
            "authorize_answers" => true,
            "bookQuestionnaires" => [array(
                    "id" => 1,
                    "allow_attempts" => true,
                    "attempts_number" => 12
            )]);
        $compet = CompetitionQuery::create()->findOne();
        $book = $manager->addBook($post, 26, 2, $compet);
        $this->assertEquals("testLivre", $book->getTitle());
        $this->assertEquals("testAuteur", $book->getAuthor());
        $this->assertEquals(true, $book->getAuthorizeNotices());
    }
    protected function getManager(){
        return $this->getAppClient()->getContainer()->get('bns.competition.book.manager');
    }
}
