<?php
namespace BNS\App\UserBundle\Tests\Controller;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\UserBundle\Listener\SecuritySwitchUserListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;

class SecuritySwitchUserListenerTest extends AppWebTestCase
{
    public function testOnSecuritySwitchUser()
    {
        $user = new User();
        $listener = new SecuritySwitchUserListener();
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());

        $session->set('bns_context', 'foo');
        $request->setSession($session);

        $event = new SwitchUserEvent($request, $user);
        $listener->onSecuritySwitchUser($event);
        $this->assertEquals(false, $session->has('bns_context'));

    }
}
