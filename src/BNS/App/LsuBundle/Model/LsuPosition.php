<?php

namespace BNS\App\LsuBundle\Model;

use BNS\App\LsuBundle\Model\om\BaseLsuPosition;

class LsuPosition extends BaseLsuPosition
{

    public function getAchievementRaw()
    {
        return $this->achievement + 1;
    }

}
