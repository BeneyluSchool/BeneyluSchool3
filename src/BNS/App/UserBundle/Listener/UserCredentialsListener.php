<?php

namespace BNS\App\UserBundle\Listener;

use BNS\App\UserBundle\Credentials\UserCredentialsManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Class UserCredentialsListener
 *
 * @package BNS\App\UserBundle\Listener
 */
class UserCredentialsListener
{

    /**
     * @var UserCredentialsManager
     */
    protected $manager;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var Router
     */
    protected $router;

    public function __construct(UserCredentialsManager $manager, ContainerInterface $container, Router $router)
    {
        $this->manager = $manager;
        $this->container = $container;
        $this->router = $router;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY);

        if (!$this->container->get('bns.right_manager')->hasRightSomeWhere('MAIN_UPDATE_CREDENTIAL')) {
            // user is not able to change credentials, don't ask him to
            return;
        }

        if ($this->manager->haveCredentialsExpired()) {
            $session->set(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY, true);
        }
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->get('_format') !== 'json' && !in_array($request->get('_route'), [
                'BNSAppMainBundle_front',
                'disconnect_user',
                'home',
                'home_locale',
                'bns_my_avatar',
                'user_front_registration_step',
                'user_front_cgu_validate',
                '_wdt',
                '_profiler',
            ]))
        {
            $session = $request->getSession();
            if ($session->get(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY)) {
                $response = new RedirectResponse($this->router->generate('account_password_change', [], true));
                $event->setResponse($response);
            }
        }
    }

}
