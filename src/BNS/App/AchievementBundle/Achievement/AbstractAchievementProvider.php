<?php

namespace BNS\App\AchievementBundle\Achievement;

use Symfony\Component\Yaml\Yaml;

/**
 * Class AbstractAchievementProvider
 *
 * @package BNS\App\AchievementBundle\Achievement
 */
abstract class AbstractAchievementProvider
{

    protected $achievements;

    /**
     * Module unique name concerned by these achievements
     *
     * @return string
     */
    abstract public function getName();

    public function __construct()
    {
        // use a reflection class to get proper paths in child classes
        $reflection = new \ReflectionClass($this);
        $this->directory = dirname($reflection->getFileName()) . '/../Resources/achievement';
    }

    /**
     * List of available achievements.
     *
     * @return array
     */
    public function getAchievements()
    {
        if (!$this->achievements) {
            $this->achievements = Yaml::parse(file_get_contents($this->directory . '/achievements.yml'));

            if (!is_array($this->achievements)) {
                throw new \RuntimeException('The "Resources/achievement/achievements.yml" file is missing');
            }
        }

        return $this->achievements;
    }

}
