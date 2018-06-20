<?php
namespace BNS\App\UserBundle\Listener;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class AAFUserListener
{

    /**
     * @var RouterInterface
     */
    private $router;

    private $listenerEscapeRoutes;

    public function __construct(RouterInterface $router, $listenerEscapeRoutes)
    {
        $this->router = $router;
        $this->listenerEscapeRoutes = $listenerEscapeRoutes;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove('need_aaf_parent_confirmation');
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            // A parent without AAF that has children with AAF must confirm his account.
            if ($user->getHighRoleId() == 9 && !$user->getAafId()) {
                foreach ($user->getChildren() as $child) {
                    if ($child->getAafId()) {
                        $session->set('need_aaf_parent_confirmation', true);
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
            if ($session->get('need_aaf_parent_confirmation')) {
                $event->setResponse(new RedirectResponse($this->router->generate('account_link_parent')));
                return;
            }
        }
    }

}
