<?php
namespace BNS\App\UserBundle\Listener;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class CGUListener
{

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    public function __construct(RouterInterface $router, BNSUserManager $userManager, BNSGroupManager $groupManager)
    {
        $this->router = $router;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove('need_cgu_validation');
        $user = $event->getAuthenticationToken()->getUser();
        $cguEnabled = false;
        $cguVersion = null;

        if ($user instanceof User && $user->isAdult()) {
            foreach ($this->userManager->setUser($user)->getGroupsUserBelong() as $group) {
                if ($group->getType() === 'ENVIRONMENT') {
                    continue;
                }
                /** @var Group|false $env */
                $env = $this->groupManager->getEnvironment($group);
                if ($env) {
                    $cguEnabled = $this->groupManager->getAttributeStrict($env, 'CGU_ENABLED', null);
                    if ($cguEnabled) {
                        $cguVersion = $this->groupManager->getAttributeStrict($env, 'CGU_VERSION', null);
                    }
                    if (false === $cguEnabled || true === $cguEnabled && $cguVersion) {
                        break;
                    }
                }
            }

            if ($cguEnabled) {
                if (!$user->getCguVersion() || !$user->getCguValidation() || ($user->getCguVersion() != $cguVersion) ) {
                    $session->set('need_cgu_validation', true);
                }
                return;
            }
        }
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            // don't do anything if it's not the master request
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        if ($request->get('_format') !== 'json' && !in_array($request->get('_route'), [
                'bns_app_admin_certifier_password',
                'bns_app_admin_certifier_username',
                'disconnect_user',
                'home',
                'home_locale',
                'bns_my_avatar',
                'restricted_access',
                'user_front_cgu_validate',
                '_wdt',
                '_profiler',
            ]) && 0 !== strpos($request->get('_route'), '_profiler'))
        {
            if ($session->get('need_cgu_validation')) {
                $event->setResponse(new RedirectResponse($this->router->generate('user_front_cgu_validate')));
            }
        }

        if (in_array($request->get('_route'), ['user_front_registration_step'])) {
            $session->remove('need_cgu_validation');
        }
    }

}
