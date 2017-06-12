<?php
namespace BNS\App\UserBundle\Listener;

use Symfony\Component\Security\Http\Event\SwitchUserEvent;

class SecuritySwitchUserListener
{
    public function onSecuritySwitchUser(SwitchUserEvent $event)
    {
        $request = $event->getRequest();
        $request->getSession()->remove('bns_context');
    }
}