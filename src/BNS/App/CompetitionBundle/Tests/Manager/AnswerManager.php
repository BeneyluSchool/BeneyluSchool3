<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 09/05/2017
 * Time: 17:44
 */

namespace BNS\App\CompetitionBundle\Tests\Manager;


use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;

class AnswerManager extends AppWebTestCase
{
    public function testAnswerManager(){
        $answerManager = $this->getManager();
        $widget = WorkshopWidgetQuery::create()->findPk(1);
        $answer = array(1);
        $reponse = $answerManager->compareAnswers($answer,$widget);
        $this->assertArrayHasKey('user', $reponse);
    }

    protected function getManager(){
        return $this->getAppClient()->getContainer()->get('bns.competition.answer.manager');
    }

}
