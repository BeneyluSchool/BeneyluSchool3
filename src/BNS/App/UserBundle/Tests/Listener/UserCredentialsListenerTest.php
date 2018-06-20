<?php
namespace BNS\App\UserBundle\Tests\Controller;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\UserBundle\Credentials\UserCredentialsManager;
use BNS\App\UserBundle\Listener\UserCredentialsListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class UserCredentialsListenerTest extends AppWebTestCase
{

    public function testOnInteractiveLoginSessionAttributeRemoved()
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());

        $session->set(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY, 'foo');
        $request->setSession($session);

        $event = new InteractiveLoginEvent($request, $token);

        $listener = $this->getListener();

        $listener->onInteractiveLogin($event);
        $this->assertEquals(false, $session->has(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY));
    }

    public function testOnInteractiveLoginSessionSet()
    {
        $user = new User();
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new InteractiveLoginEvent($request, $token);
        $that = $this;
        $listener = $this->getListener(function($userCredentialMock) use ($that){
            /** @var $userCredentialMock \PHPUnit_Framework_MockObject_MockObject */
            $userCredentialMock->expects($that->once())
                ->method('haveCredentialsExpired')
                ->will($that->returnValue(true));
            return $userCredentialMock;
        }, null, function($rightManagerMock) use ($that){
            /** @var $rightManagerMock \PHPUnit_Framework_MockObject_MockObject */
            $rightManagerMock->expects($that->atLeastOnce())
                ->method('hasRightSomeWhere')
                ->withConsecutive(
                    ['MAIN_UPDATE_CREDENTIAL'],
                    ['ADMIN_UPDATE_CREDENTIAL']
                )
                ->willReturnOnConsecutiveCalls([true, false]);

            return $rightManagerMock;
        });
        $listener->onInteractiveLogin($event);
        $this->assertEquals(true, $session->get(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY));
    }

    public function testOnKernelRequestMaster()
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();

        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);
        $that = $this;
        $listener = $this->getListener(null, function($routerMock) use ($that){
            /** @var $routerMock \PHPUnit_Framework_MockObject_MockObject */
            $routerMock->expects($that->once())
                ->method('generate')
                ->will($that->returnValue(true));
            return $routerMock;
        });

        $session->set(UserCredentialsManager::NEED_UPDATE_CREDENTIAL_SESSION_KEY, 'foo');

        $listener->onKernelRequest($event);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $event->getResponse());

    }

    public function testOnKernelRequestNotMaster()
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();

        $event = new GetResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener = $this->getListener();

        $listener->onKernelRequest($event);
        $this->assertNull($listener->onKernelRequest($event));
    }

    protected function getListener(callable $mockUserCredential = null, callable $mockRouter  = null, callable $mockRightManager = null)
    {
        $userCredentialBuilder = $this->getMockBuilder('BNS\App\UserBundle\Credentials\UserCredentialsManager');
        $userCredentialBuilder->disableOriginalConstructor();
        $userCredentialMock = $userCredentialBuilder->getMock();

        if ($mockUserCredential) {
            $userCredentialMock = $mockUserCredential($userCredentialMock);
        }

        $routerBuilder = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Routing\Router');
        $routerBuilder->disableOriginalConstructor();
        $routerMock = $routerBuilder->getMock();

        if ($mockRouter) {
            $routerMock = $mockRouter($routerMock);
        }

        $rightManager = $this->getMockBuilder('BNS\App\CoreBundle\User\BNSUserManager');
        $rightManager->disableOriginalConstructor();
        $rightManagerMock = $rightManager->getMock();

        if ($mockRightManager) {
            $rightManagerMock = $mockRightManager($rightManagerMock);
        }

        $listenerEscapeRoutes = [];

        return new UserCredentialsListener($userCredentialMock, $routerMock, $rightManagerMock, $listenerEscapeRoutes);
    }

}
