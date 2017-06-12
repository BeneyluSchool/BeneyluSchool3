<?php

namespace BNS\App\MediaLibraryBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use \Twig_Extension;
use \Twig_Function_Method;

/**
 * Twig Extension for locker folder support.
 */
class LockerExtension extends Twig_Extension
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'getLockerForHomework' => new Twig_Function_Method($this, 'getLockerForHomework'),
        );
    }

    public function getLockerForHomework($homework, $group = null)
    {
        return $this->container->get('bns.media_folder.locker_manager')->getLockerForHomework($homework, $group);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'locker';
    }

}
