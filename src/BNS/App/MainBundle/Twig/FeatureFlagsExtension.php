<?php

namespace BNS\App\MainBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeatureFlagsExtension
 *
 * @package BNS\App\MainBundle\Twig
 */
class FeatureFlagsExtension extends \Twig_Extension
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
            new \Twig_SimpleFunction('has_feature_flag', array($this, 'hasFeatureFlag'), array(
                'is_safe' => array('html'),
            )),
        );
    }

    public function hasFeatureFlag($name)
    {
        return $this->container->get('qandidate.toggle.manager')->active(
            $name,
            $this->container->get('bns.toggle.context_factory')->createContext()
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'feature_flags_extension';
    }
}
