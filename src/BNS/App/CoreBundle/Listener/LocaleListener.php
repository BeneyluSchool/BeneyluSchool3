<?php

namespace BNS\App\CoreBundle\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LocaleListener implements EventSubscriberInterface
{
	/**
	 * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
	 */
	public function setLocale(GetResponseEvent $event)
	{
		if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
			return;
		}
		
		$request = $event->getRequest();
		
		// If user is not authenticated
		if (BNSAccess::isConnectedUser()) {
			$request->setLocale(BNSAccess::getUser()->getLang());
		}
		else {
			if ('undefined' == $request->getLocale()) {
				// Get the prefered user language
				$request->setLocale($request->getPreferredLanguage());
			}
		}
	}
	
	static public function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 128),
        );
    }
}