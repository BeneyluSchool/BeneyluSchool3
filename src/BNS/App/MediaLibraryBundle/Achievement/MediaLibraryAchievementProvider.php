<?php

namespace BNS\App\MediaLibraryBundle\Achievement;

use BNS\App\AchievementBundle\Achievement\AbstractAchievementProvider;

/**
 * Class MediaLibraryAchievementProvider
 *
 * @package BNS\App\MediaLibraryBundle\Achievement
 */
class MediaLibraryAchievementProvider extends AbstractAchievementProvider
{

    /**
     * Module unique name concerned by these achievements
     *
     * @return string
     */
    public function getName()
    {
        return 'MEDIA_LIBRARY';
    }

}
