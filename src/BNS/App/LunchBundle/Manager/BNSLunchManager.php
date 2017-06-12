<?php

namespace BNS\App\LunchBundle\Manager;

use Symfony\Component\HttpKernel\Exception\HttpException;
use BNS\App\LunchBundle\Model\LunchWeek;
use BNS\App\LunchBundle\Model\LunchDay;


class BNSLunchManager
{
    public function createWeek()
    {
        $week = new LunchWeek();

        for($i=0; $i<5; $i++ ){
            $day = new LunchDay();
            $day->setDayOfWeek($i+1);
            $week->addLunchDay($day);
        }

        return $week;
    }
}
