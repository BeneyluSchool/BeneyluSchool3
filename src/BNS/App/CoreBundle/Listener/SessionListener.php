<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\EventListener\SessionListener as BaseSessionListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class SessionListener extends BaseSessionListener
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        parent::onKernelRequest($event);

        if (!$event->isMasterRequest()) {
            return ;
        }

        // Registering container in a static class
        // TODO move this away
        BNSAccess::setContainer($this->container);

        // Fix for IE on not same domain iframes
        // @see http://www.softwareprojects.com/resources/programming/t-how-to-get-internet-explorer-to-use-cookies-inside-1612.html
        // @see http://www.w3.org/TR/2000/CR-P3P-20001215/#compact_policy_vocabulary
        // disable this in test env
        // TODO use symfony response listener to add this the right way
        if (false === strpos($this->container->getParameter('kernel.environment'), '_test')) {
            header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
        }
    }

    protected function getSession()
    {
        if (!$this->container->has('session')) {
            return;
        }

        return $this->container->get('session');
    }
}
