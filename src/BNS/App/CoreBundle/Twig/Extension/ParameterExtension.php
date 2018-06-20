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
            'parameter' => new \Twig_Function_Method($this, 'getParameter', array()),
            'bnsStoreLink' => new \Twig_SimpleFunction('bnsStoreLink', [$this, 'getBnsStoreLinks'], ['is_safe' => ['html']]),
            'bnsLocaleLink' => new \Twig_SimpleFunction('bnsLocaleLink', [$this, 'getBnsLocaleLinks'], ['is_safe' => ['html']])
        );
    }

    /**
	 * @param string $parameterName
	 *
	 * @return mixed
	 */
    public function getParameter($parameterName)
    {
        if($this->container->hasParameter($parameterName))
        {
            return $this->container->getParameter($parameterName);
        }else{
            return false;
        }
    }

    public function getBnsLocaleLinks($item, $locale = null)
    {
        return $this->container->get('bns_app_core.routing.bns_locale_links')->getLink($item, $locale);
    }

    public function getBnsStoreLinks($item, $store = null)
    {
        return $this->container->get('bns_app_core.routing.bns_store_links')->getLink($item, $store);
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
