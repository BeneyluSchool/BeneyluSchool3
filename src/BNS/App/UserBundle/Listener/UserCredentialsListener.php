<?php

namespace BNS\App\UserBundle\Listener;

use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\UserBundle\Credentials\UserCredentialsManager;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
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
     * @var Router
     */
    protected $router;

    /**
     * @var BNSUserManager
     */
    protected $rightManager;

    private $listenerEscapeRoutes;

    public function __construct(UserCredentialsManager $manager, Router $router, BNSUserManager $rightManager, $listenerEscapeRoutes)
    {
        $this->manager = $manager;
        $this->router = $router;
        $this->rightManager = $rightManager;
        $this->listenerEscapeRoutes = $listenerEscapeRoutes;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY);

        $token = $event->getAuthenticationToken();
        if ($token instanceof RememberMeToken) {
            // do not force update on remember me token
            return;
        }

        if (!$this->rightManager->hasRightSomeWhere('MAIN_UPDATE_CREDENTIAL')
            || $this->rightManager->hasRightSomeWhere('ADMIN_UPDATE_CREDENTIAL')) {
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
        if ($request->get('_format') !== 'json' && !in_array($request->get('_route'), $this->listenerEscapeRoutes))
        {
            $session = $request->getSession();
            if ($session->get(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY)) {
                $response = new RedirectResponse($this->router->generate('account_password_change', [], true));
                $event->setResponse($response);
            }
        }
    }

}
