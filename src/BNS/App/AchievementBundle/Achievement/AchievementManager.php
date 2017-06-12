<?php

namespace BNS\App\AchievementBundle\Achievement;

use BNS\App\AchievementBundle\Model\AchievementQuery;
use BNS\App\CoreBundle\Model\User;

/**
 * Class AchievementManager
 *
 * @package BNS\App\AchievementBundle\Achievement
 */
class AchievementManager
{

    /**
     * Array of registered providers
     *
     * @var array|AbstractAchievementProvider[]
     */
    protected $providers = [];

    public function addProvider(AbstractAchievementProvider $provider)
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Gets the list of registered achievement providers
     *
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Grants the given achievement to user, if it didn't already have it.
     *
     * @param string $name
     * @param User $user
     */
    public function grant($name, User $user)
    {
        $this->validateAchievementName($name);

        $achievement = AchievementQuery::create()
            ->filterByUser($user)
            ->filterByUniqueName($name)
            ->findOneOrCreate()
        ;
        if ($achievement->isNew()) {
            $achievement->save();
        }
    }

    /**
     * Checks that the given achievement name is valid (ie it exists in one of the registered providers).
     *
     * @param string $name
     * @return bool
     */
    public function validateAchievementName($name)
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getAchievements() as $achievement) {
                if ($achievement['name'] === $name) {
                    return true;
                }
            }
        }

        throw new \InvalidArgumentException('Invalid achievement name: '.$name);
    }

}
