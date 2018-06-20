<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 09/05/2017
 * Time: 17:52
 */

namespace BNS\App\CompetitionBundle\Tests\Manager;


use BNS\App\CompetitionBundle\Model\CompetitionQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CompetitionManager extends AppWebTestCase
{
    public function testCompetitionManager(){
        $competitionManager = $this->getManager();
        $compet = CompetitionQuery::create()->findPk(1);
        $request = new Request();
        //$competitionManager->getPercentCompetition($compet, $request);
        $ids = $competitionManager->getCompetitionCanAccessIds();

    }

    protected function getManager(){
        return $this->getAppClient()->getContainer()->get('bns.competition.competition.manager');
    }
}
