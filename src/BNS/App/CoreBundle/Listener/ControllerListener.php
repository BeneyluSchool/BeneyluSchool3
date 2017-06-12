<?php

namespace BNS\App\CoreBundle\Listener;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use ReflectionMethod;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ControllerListener
{
	/**
	 * @var Reader
	 */
	private $reader;
    private $container;
	
	/**
	 * @param Reader $reader
	 */
	public function __construct($reader, $container)
	{
		$this->reader = $reader;
        $this->container = $container;
	}

	/**
	 * @param FilterControllerEvent $event
	 */
	public function onCoreController(FilterControllerEvent $event)
	{
/*
        if(
            $event->getRequest()->getUri() != $this->container->get('router')->generate('home',array(),true) &&
            !$this->container->get('bns.right_manager')->isAuthenticated() &&
            !$this->container->get('kernel')->isDebug()
        )
        {
            throw new AccessDeniedException();
        }
*/

		if (!is_array($controller = $event->getController())) {
			return;
		}
		
		$method = new ReflectionMethod($controller[0], $controller[1]);
		if (!$annotations = $this->reader->getMethodAnnotations($method)) {
			return;
		}
		
		foreach ($annotations as $annotation) {
			if ($annotation instanceof Rights || $annotation instanceof RightsSomeWhere) {
				$annotation->execute($controller[0]->get('bns.right_manager'), $event->getRequest()->getUri());
			}
			elseif ($annotation instanceof Anon) {
				$annotation->execute($controller[0]->get('router'));
			}
		}
	}
}