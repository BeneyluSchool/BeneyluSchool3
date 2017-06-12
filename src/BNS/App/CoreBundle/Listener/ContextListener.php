<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Exception\NoContextException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ContextListener
 *
 * @package BNS\App\CoreBundle\Listener
 */
class ContextListener
{

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if ($event->getException() instanceof NoContextException) {
            $response = new RedirectResponse($this->router->generate('context_no_group', array(), true));
            $event->setResponse($response);
        }
    }

}
