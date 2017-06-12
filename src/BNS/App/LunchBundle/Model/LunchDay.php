<?php

namespace BNS\App\LunchBundle\Model;

use BNS\App\LunchBundle\Model\om\BaseLunchDay;

class LunchDay extends BaseLunchDay
{
    const STATUS_NORMAL = 1;
    const STATUS_SPECIAL = 2;
    const STATUS_NO_LUNCH = 3;
}
