<?php

namespace BNS\App\MainBundle\Achievement;

use BNS\App\AchievementBundle\Achievement\AbstractAchievementProvider;

/**
 * Class MainAchievementProvider
 *
 * @package BNS\App\MainBundle\Achievement
 */
class MainAchievementProvider extends AbstractAchievementProvider
{

    /**
     * Module unique name concerned by these achievements
     *
     * @return string
     */
    public function getName()
    {
        return 'MAIN';
    }

}
