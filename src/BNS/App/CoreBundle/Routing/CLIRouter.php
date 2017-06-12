<?php

namespace BNS\App\CoreBundle\Routing;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CLIRouter
{
	/**
	 * @var \Symfony\Bundle\FrameworkBundle\Routing\Router
	 */
	private $router;
	
	/**
	 * @var string The base application URL (parameter)
	 */
	private $baseUrl;
	
	/**
	 * 
	 * @param Router $router
	 * @param string $baseUrl
	 */
	public function __construct($router, $baseUrl)
    {
        $this->router  = $router;
		
		// URL without the ending slash
		$length = strlen($baseUrl);
		if (substr($baseUrl, $length - 1, $length) == '/') {
			$baseUrl = substr($baseUrl, 0, -1);
		}
		
        $this->baseUrl = $baseUrl;
    }
	
	/**
     * {@inheritdoc}
     */
    public function generate($name, $parameters = array(), $absolute = false)
    {
		$url = $this->router->generate($name, $parameters, false);
		if (!$absolute) {
			return $url;
		}
		
		return $this->baseUrl . $url;
    }
}