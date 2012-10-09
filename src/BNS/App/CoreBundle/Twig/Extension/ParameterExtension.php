<?php
namespace BNS\App\CoreBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ParameterExtension extends \Twig_Extension
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
            'parameter' => new \Twig_Function_Method($this, 'getParameter', array())
        );
    }

    /**
	 * @param string $parameterName
	 * 
	 * @return mixed 
	 */
    public function getParameter($parameterName)
    {
		return $this->container->getParameter($parameterName);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'parameter_extension';
    }
}
