<?php

namespace BNS\App\StarterKitBundle\StarterKit;

use Symfony\Component\Yaml\Yaml;

/**
 * Class AbstractStarterKitProvider
 *
 * @package BNS\App\StarterKitBundle\StarterKit
 */
abstract class AbstractStarterKitProvider
{

    protected $steps;

    protected $handlers = [];

    /**
     * Module unique name concerned by this starter kit
     *
     * @return string
     */
    abstract public function getName();

    public function __construct()
    {
        // use a reflection class to get proper paths in child classes
        $reflection = new \ReflectionClass($this);
        $this->directory = dirname($reflection->getFileName()) . '/../Resources/starter_kit';
    }

    /**
     * List of starter kit levels, each of which is a list of steps.
     *
     * @return array
     */
    public function getSteps()
    {
        if (!$this->steps) {
            $this->steps = Yaml::parse(file_get_contents($this->directory . '/steps.yml'));

            if (!is_array($this->steps)) {
                throw new \RuntimeException('The "Resources/starter_kit/steps.yml" file is missing');
            }
        }

        return $this->steps;
    }

    /**
     * Gets the handler for the given step number, if any
     *
     * @param string $stepName
     * @return callable
     */
    public function getHandler($stepName)
    {
        return isset($this->handlers[$stepName]) ? $this->handlers[$stepName] : null;
    }

    /**
     * Sets a handler for the given step number. Handlers are callables that are executed when their step is completed.
     * They receive the starter kit state and the step as parameter.
     * @see StarterKitManager::triggerEvents()
     *
     * @param $stepNumber
     * @param $callable
     */
    public function setHandler($stepNumber, $callable)
    {
        $this->handlers[$stepNumber] = $callable;
    }

}
