<?php

namespace BNS\App\StarterKitBundle\StarterKit;

use BNS\App\AchievementBundle\Achievement\AchievementManager;
use BNS\App\CoreBundle\Model\User;
use BNS\App\StarterKitBundle\Model\StarterKitState;
use BNS\App\StarterKitBundle\Model\StarterKitStateQuery;

/**
 * Class StarterKitManager
 *
 * @package BNS\App\StarterKitBundle\StarterKit
 */
class StarterKitManager
{

    /**
     * Array of registered providers
     *
     * @var array|AbstractStarterKitProvider[]
     */
    protected $providers = [];

    /**
     * @var AchievementManager
     */
    protected $achievementManager;

    public function __construct(AchievementManager $achievementManager)
    {
        $this->achievementManager = $achievementManager;
    }

    public function addProvider(AbstractStarterKitProvider $provider)
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Gets the list of registered starter kit providers
     *
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param string $name
     * @return AbstractStarterKitProvider
     */
    public function getProvider($name)
    {
        $this->validateName($name);

        return $this->providers[$name];
    }

    /**
     * Gets the starter kit state for the given app (name) and user
     *
     * @param string $name
     * @param User $user
     * @return StarterKitState
     */
    public function getState($name, User $user)
    {
        return StarterKitStateQuery::create()
            ->filterByName($name)
            ->filterByUser($user)
            ->findOne()
        ;
    }

    /**
     * Creates a starter kit state for the given app (name) and user
     *
     * @param string $name
     * @param User $user
     * @return StarterKitState
     */
    public function createState($name, User $user)
    {
        $otherEnabledStates = StarterKitStateQuery::create()
            ->filterByUser($user)
            ->filterByEnabled(true)
            ->count()
        ;
        $this->validateName($name);
        $state = new StarterKitState();
        $state->setUser($user)
            ->setName($name)
            ->setEnabled(!$otherEnabledStates) // do not enable this if another one is already enabled
            ->save();

        return $state;
    }

    /**
     * Sets the last step of the given state, after checking that step name exists.
     *
     * @param StarterKitState $state
     * @param string $stepName
     * @param bool $trigger
     * @return bool
     */
    public function setLastStep(StarterKitState $state, $stepName, $trigger = false)
    {
        $provider = $this->getProvider($state->getName());
        foreach ($provider->getSteps() as $level => $stepsByLevel) {
            foreach ($stepsByLevel as $step) {
                if ($step['step'] === $stepName) {
                    $state->setLastStep($stepName);
                    // assume that if last step is on level x, user has completed level x - 1
                    $state->setMaxLevel(max($state->getMaxLevel(), $level - 1));
                    if ($trigger) {
                        $this->triggerEvents($state, $step);
                    }
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Marks the given state as done, ie the user has completed all levels.
     *
     * @param StarterKitState $state
     */
    public function setDone(StarterKitState $state)
    {
        $steps = $this->getProvider($state->getName())->getSteps();

        // go to last level, and get its key
        end($steps);
        $maxLevel = key($steps);

        $state->setMaxLevel($maxLevel)
            ->setLastStep(null)
        ;
    }

    public function triggerEvents(StarterKitState $state, array $step)
    {
        if ('achievement' === $step['type']) {
            $this->achievementManager->grant($step['achievement'], $state->getUser());
        }
        if ($handler = $this->getProvider($state->getName())->getHandler($step['step'])) {
            $handler($state, $step);
        }
    }

    /**
     * Checks if the given app name is valid, ie a provider with this name is registered.
     *
     * @param $name
     * @return bool
     */
    public function isValidName($name)
    {
        return isset($this->providers[$name]);
    }

    /**
     * Throws an exception if the given app name is not supported
     *
     * @param $name
     */
    private function validateName($name)
    {
        if (!$this->isValidName($name)) {
            throw new \InvalidArgumentException('Invalid starter kit app name: '.$name);
        }
    }

}
