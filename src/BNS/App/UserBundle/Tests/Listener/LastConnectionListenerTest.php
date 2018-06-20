<?php
namespace BNS\App\UserBundle\Tests\Listener;

use BNS\App\CoreBundle\Model\Profile;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\UserBundle\Listener\LastConnectionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LastConnectionListenerTest extends AppWebTestCase
{

    public function users()
    {
        return [
            ['enseignantTest', true],
            ['eleveTest', false]
        ];
    }

    public function testOnInteractiveLoginSessionAttributeRemoved()
    {
        $user = new UserTest();
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());

        $session->set('need_policy_validation', 'foo');
        $session->set('previous_connection_message', 'bar');
        $request->setSession($session);

        $event = new InteractiveLoginEvent($request, $token);

        $listener = $this->getListener();
        $listener->onInteractiveLogin($event);
        $this->assertEquals(false, $session->has('need_policy_validation'));
    }

    /**
     * @dataProvider users
     */
    public function testOnInteractiveLoginWithDate($username, $isAdult)
    {
        $user = new UserTest();
        $user->setLogin($username);
        $user->setFirstName('Test');
        $user->setPreviousConnection(date('Y-m-d H:i:s', strtotime("-1 month")));
        $user->setLang('fr');
        if($isAdult) {
            $user->setHighRoleId(7);
        } else {
            $user->setHighRoleId(8);
        }

        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new InteractiveLoginEvent($request, $token);

        $that = $this;
        $listener = $this->getListener(function($translatorMock) use ($that, $isAdult){
            if($isAdult) {
                /** @var $translatorMock \PHPUnit_Framework_MockObject_MockObject */
                $translatorMock->expects($that->once())
                    ->method('trans')
                    ->with($that->equalTo('ADULT_WELCOME_PREVIOUS_CONNECTION'))
                    ->will($that->returnValue('ADULT_WELCOME_PREVIOUS_CONNECTION_TRANSLATED'));
                return $translatorMock;
            } else {
                /** @var $translatorMock \PHPUnit_Framework_MockObject_MockObject */
                $translatorMock->expects($that->once())
                    ->method('trans')
                    ->with($that->equalTo('CHILD_WELCOME_PREVIOUS_CONNECTION'))
                    ->will($that->returnValue('CHILD_WELCOME_PREVIOUS_CONNECTION_TRANSLATED'));
                return $translatorMock;
            }

        },function($dateI18nMock) use ($that){
            /** @var $dateI18nMock \PHPUnit_Framework_MockObject_MockObject */
            $dateI18nMock->method('process')
                ->will($that->returnValue(true));
            return $dateI18nMock;
        });
        $listener->onInteractiveLogin($event);
        $this->assertEquals(date('Y-m-d H:i:s'), $user->getLastConnection('Y-m-d H:i:s'));
        if ($isAdult) {
            $this->assertEquals('ADULT_WELCOME_PREVIOUS_CONNECTION_TRANSLATED', $session->getFlashBag()->get('success')[0]);
        } else {
            $this->assertEquals('CHILD_WELCOME_PREVIOUS_CONNECTION_TRANSLATED', $session->getFlashBag()->get('success')[0]);
        }
        $this->assertEquals(true, $session->has('previous_connection_message'));
    }


    /**
     * @dataProvider users
     */
    public function testOnInteractiveLoginWithoutDate($username, $isAdult)
    {
        $user = new UserTest();
        $user->setLogin($username);
        $user->setFirstName('Test');
        $user->setLang('fr');
        if($isAdult) {
            $user->setHighRoleId(7);
        } else {
            $user->setHighRoleId(8);
        }

        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $event = new InteractiveLoginEvent($request, $token);

        $that = $this;
        $listener = $this->getListener(function($translatorMock) use ($that, $isAdult){
            if ($isAdult) {
                /** @var $translatorMock \PHPUnit_Framework_MockObject_MockObject */
                $translatorMock->expects($that->once())
                    ->method('trans')
                    ->with($that->equalTo('ADULT_WELCOME_FIRST_CONNECTION'))
                    ->will($that->returnValue('ADULT_WELCOME_FIRST_CONNECTION_TRANSLATED'));
                return $translatorMock;
            } else {
                /** @var $translatorMock \PHPUnit_Framework_MockObject_MockObject */
                $translatorMock->expects($that->once())
                    ->method('trans')
                    ->with($that->equalTo('CHILD_WELCOME_FIRST_CONNECTION'))
                    ->will($that->returnValue('CHILD_WELCOME_FIRST_CONNECTION_TRANSLATED'));
                return $translatorMock;
            }

        },function($dateI18nMock) use ($that, $user){
            /** @var $dateI18nMock \PHPUnit_Framework_MockObject_MockObject */
            $dateI18nMock->method('process')
                ->will($that->returnValue(true));
            return $dateI18nMock;
        });
        $listener->onInteractiveLogin($event);
        $this->assertEquals(date('Y-m-d H:i:s'), $user->getLastConnection()->format('Y-m-d H:i:s'));
        $this->assertEquals(true, $session->has('previous_connection_message'));
        if ($isAdult) {
            $this->assertEquals('ADULT_WELCOME_FIRST_CONNECTION_TRANSLATED', $session->getFlashBag()->get('success')[0]);
        } else {
            $this->assertEquals('CHILD_WELCOME_FIRST_CONNECTION_TRANSLATED', $session->getFlashBag()->get('success')[0]);
        }
    }

    protected function getListener(callable $mockTranslator = null, callable $mockDateI18n = null)
    {
        $translatorBuilder = $this->getMockBuilder('Symfony\Component\Translation\TranslatorInterface');
        $translatorBuilder->disableOriginalConstructor();
        $translatorMock = $translatorBuilder->getMock();

        if ($mockTranslator) {
            $translatorMock = $mockTranslator($translatorMock);
        }

        $dateI18nBuilder = $this->getMockBuilder('BNS\App\CoreBundle\Date\DateI18n');
        $dateI18nBuilder->disableOriginalConstructor();
        $dateI18nMock = $dateI18nBuilder->getMock();

        if ($mockDateI18n) {
            $dateI18nMock = $mockDateI18n($dateI18nMock);
        }

        return new LastConnectionListener($translatorMock, $dateI18nMock);
    }
}

Class UserTest extends User
{
    public function save(\PropelPDO $con = null)
    {
        return true;
    }
}
