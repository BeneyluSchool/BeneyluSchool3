<?php

namespace BNS\App\MainBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RestrictedEnvironmentExtension
 *
 * @package BNS\App\MainBundle\Twig
 */
class RestrictedEnvironmentExtension extends \Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('hasRestrictedEnvironment', array($this, 'hasRestrictedEnvironment'), array(
                'is_safe' => array('html'),
            )),
        );
    }

    public function hasRestrictedEnvironment()
    {
        $rightManager = $this->container->get('bns.right_manager');
        $currentGroup = $rightManager->getCurrentGroup();

        $environmentSettings =  $this->container->get('bns.restricted_access.manager')->getEnvironmentSettings($currentGroup);

        return $environmentSettings['enabled'];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'restricted_environment_extension';
    }
}
