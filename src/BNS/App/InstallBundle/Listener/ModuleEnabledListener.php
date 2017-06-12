<?php

namespace BNS\App\InstallBundle\Listener;

use \Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use \Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use \Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ModuleEnabledListener
{
	/**
	 * @var \BNS\App\InstallBundle\Install\InstallManager
	 */
	private $installManager;

	/**
	 * @var \Symfony\Bundle\FrameworkBundle\Routing\Router
	 */
	private $router;
	
	/**
	 * @param \BNS\App\InstallBundle\Install\InstallManager $installManager
	 */
	public function __construct($installManager, $router)
	{
		$this->installManager = $installManager;
		$this->router		  = $router;
	}
	
	/**
	 * @param FilterControllerEvent $event
	 */
	public function onKernelController(FilterControllerEvent $event)
	{
        $route = $event->getRequest()->attributes->get('_route');

        if($route == "_monitoring")
        {
            return;
        }


		if (!is_array($controller = $event->getController())) {
			return;
		}

		if (!$this->installManager->isModuleEnabled($controller[0])) {
			$redirectResponse = new RedirectResponse($this->router->generate('home', array(), true));
			$redirectResponse->send();
		}
	}
}