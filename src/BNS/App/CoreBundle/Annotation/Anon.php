<?php

namespace BNS\App\CoreBundle\Annotation;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Router;

use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Annotation
 */
final class Anon
{
	/**
	 * @var string Redirect route
	 */
	private $route;
	
	/**
	 * @var array 
	 */
	private $params;
	
	/**
	 * @param array $data
	 */
	public function __construct($data)
	{
		$this->route	= isset($data['value']) ? $data['value'] : 'BNSAppClassroomBundle_front';
		$this->params	= isset($data['params']) ? $data['params'] : array();
	}
	
	/**
	 * @param \Symfony\Component\Routing\Router $router
	 * 
	 * @return RedirectResponse 
	 */
	public function execute(Router $router)
	{
		if (BNSAccess::isConnectedUser()) {
			$response = new RedirectResponse($router->generate($this->route, $this->params, true));
			
			return $response->send();
		}
	}
}