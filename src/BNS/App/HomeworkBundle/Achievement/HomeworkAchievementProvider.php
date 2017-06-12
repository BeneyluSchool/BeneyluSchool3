<?php

namespace BNS\App\HomeworkBundle\Achievement;

use BNS\App\AchievementBundle\Achievement\AbstractAchievementProvider;

/**
 * Class HomeworkAchievementProvider
 *
 * @package BNS\App\HomeworkBundle\Achievement
 */
class HomeworkAchievementProvider extends AbstractAchievementProvider
{

    /**
     * Module unique name concerned by these achievements
     *
     * @return string
     */
    public function getName()
    {
        return 'HOMEWORK';
    }

}
