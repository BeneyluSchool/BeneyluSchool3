<?php

namespace BNS\App\CoreBundle\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use BNS\App\CoreBundle\Access\BNSAccess;

class SessionListener implements EventSubscriberInterface
{
    private $container;
    private $autoStart;

    public function __construct(ContainerInterface $container, $autoStart = false)
    {
        $this->container = $container;
        $this->autoStart = $autoStart;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        if($route == "_monitoring")
        {
            return;
        }

        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        if (!$this->container->has('session')) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->container->has('session') || $request->hasSession()) {
            return;
        }

        $request->setSession($session = $this->container->get('session'));

        if ($this->autoStart || $request->hasPreviousSession()) {
            $session->start();
        }

        // Fix for IE on not same domain iframes
        // @see http://www.softwareprojects.com/resources/programming/t-how-to-get-internet-explorer-to-use-cookies-inside-1612.html
        // @see http://www.w3.org/TR/2000/CR-P3P-20001215/#compact_policy_vocabulary
        //if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
        header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
        //}

        // Registering request & container in a static class
        BNSAccess::setRequest($request);
        BNSAccess::setContainer($this->container);
    }

    static public function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 128),
        );
    }
}
