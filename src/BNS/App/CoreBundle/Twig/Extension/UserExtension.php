<?php
namespace BNS\App\CoreBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

class UserExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
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
            'is_child' => new Twig_Function_Method($this, 'isChild', array()),
			'is_adult' => new Twig_Function_Method($this, 'isAdult', array())
        );
    }

    public function isChild()
    {
		return $this->container->get('bns.right_manager')->isChild();
    }
	
	public function isAdult()
    {
		return $this->container->get('bns.right_manager')->isAdult();
    }
	
	public function getName()
    {
        return 'user_extension';
    }
}
