<?php
namespace BNS\App\UserBundle\Listener;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class PolicyListener
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

    private $listenerEscapeRoutes;

    public function __construct(RouterInterface $router, BNSUserManager $userManager, BNSGroupManager $groupManager, $listenerEscapeRoutes)
    {
        $this->router = $router;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->listenerEscapeRoutes = $listenerEscapeRoutes;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove('need_policy_validation');
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            if (!$user->getPolicy()) {
                foreach ($this->userManager->setUser($user)->getGroupsUserBelong() as $group) {
                    $policyVal = $this->groupManager->setGroup($group)->getAttribute('POLICY', null);

                    if ($policyVal) {
                        $session->set('need_policy_validation', true);
                        return;
                    } elseif (null !== $policyVal) {
                        break;
                    }
                }
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
        if ($request->get('_format') !== 'json' && !in_array($request->get('_route'), $this->listenerEscapeRoutes)) {
            $session = $request->getSession();
            if ($session->get('need_policy_validation')) {
                $event->setResponse(new RedirectResponse($this->router->generate('user_front_policy_validate')));
            }
        }
    }

}
