<?php

namespace BNS\App\CoreBundle\Listener;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use ReflectionMethod;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ControllerListener
{
	/**
	 * @var Reader 
	 */
	private $reader;
	
	/**
	 * @param Reader $reader 
	 */
	public function __construct($reader)
	{
		$this->reader = $reader;
	}
	
	/**
	 * @param FilterControllerEvent $event
	 */
	public function onCoreController(FilterControllerEvent $event)
	{
		if (!is_array($controller = $event->getController())) {
			return;
		}
		
		$method = new ReflectionMethod($controller[0], $controller[1]);
		if (!$annotations = $this->reader->getMethodAnnotations($method)) {
			return;
		}
		
		foreach ($annotations as $annotation) {
			if ($annotation instanceof Rights || $annotation instanceof RightsSomeWhere) {
				$annotation->execute($controller[0]->get('bns.right_manager'));
			}
			elseif ($annotation instanceof Anon) {
				$annotation->execute($controller[0]->get('router'));
			}
		}
	}
}