<?php

namespace BNS\App\CorrectionBundle\Tests\Manager;

use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CorrectionBundle\Manager\CorrectionManager;
use Psr\Log\NullLogger;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testHasCorrectionRightWithInvalidClass()
    {
        $correctionManager = $this->getCorrectionManager();

        $user = new User();
        $this->assertFalse($correctionManager->hasCorrectionRight('fooo', $user));
    }

    public function testHasCorrectionEditRightWithInvalidClass()
    {
        $correctionManager = $this->getCorrectionManager();

        $user = new User();
        $this->assertFalse($correctionManager->hasCorrectionEditRight('fooo', $user));
    }

    public function testHasCorrectionRightWithoutGroup()
    {
        $that = $this;
        $user = new User();
        $article = new BlogArticle();

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock
                ->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock
                ->expects($that->once())
                ->method('hasRightSomeWhere')
                ->with($that->isType('string'))
                ->willReturn(true)
            ;

            return $mock;
        });

        $this->assertTrue($correctionManager->hasCorrectionRight(get_class($article), $user));
    }

    public function testHasCorrectionRightWithGroup()
    {
        $that = $this;
        $user = new User();
        $groupId = 14;

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user, $groupId) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRight')
                ->with($that->isType('string'), $groupId)
                ->willReturn(true)
            ;

            return $mock;
        });

        $this->assertTrue($correctionManager->hasCorrectionRight(get_class(new BlogArticle()), $user, $groupId));
    }

    public function testHasCorrectionRightWithoutGroupNo()
    {
        $that = $this;
        $user = new User();

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRightSomeWhere')
                ->with($that->isType('string'))
                ->willReturn(false)
            ;

            return $mock;
        });

        $this->assertFalse($correctionManager->hasCorrectionRight(get_class(new BlogArticle()), $user));
    }

    public function testHasCorrectionRightWithGroupNo()
    {
        $that = $this;
        $user = new User();
        $groupId = 14;

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user, $groupId) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRight')
                ->with($that->isType('string'), $groupId)
                ->willReturn(false)
            ;

            return $mock;
        });

        $this->assertFalse($correctionManager->hasCorrectionRight(get_class(new BlogArticle()), $user, $groupId));
    }


    public function testHasCorrectionEditRightWithoutGroup()
    {
        $that = $this;
        $user = new User();
        $article = new BlogArticle();

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock
                ->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock
                ->expects($that->once())
                ->method('hasRightSomeWhere')
                ->with($that->isType('string'))
                ->willReturn(true)
            ;

            return $mock;
        });

        $this->assertTrue($correctionManager->hasCorrectionEditRight(get_class($article), $user));
    }

    public function testHasCorrectionEditRightWithGroup()
    {
        $that = $this;
        $user = new User();
        $groupId = 14;

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user, $groupId) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRight')
                ->with($that->isType('string'), $groupId)
                ->willReturn(true)
            ;

            return $mock;
        });

        $this->assertTrue($correctionManager->hasCorrectionEditRight(get_class(new BlogArticle()), $user, $groupId));
    }

    public function testHasCorrectionEditRightWithoutGroupNo()
    {
        $that = $this;
        $user = new User();

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRightSomeWhere')
                ->with($that->isType('string'))
                ->willReturn(false)
            ;

            return $mock;
        });

        $this->assertFalse($correctionManager->hasCorrectionEditRight(get_class(new BlogArticle()), $user));
    }

    public function testHasCorrectionEditRightWithGroupNo()
    {
        $that = $this;
        $user = new User();
        $groupId = 14;

        $correctionManager = $this->getCorrectionManager(function($mock) use ($that, $user, $groupId) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('setUser')
                ->willReturnSelf()
            ;
            $mock->expects($that->once())
                ->method('hasRight')
                ->with($that->isType('string'), $groupId)
                ->willReturn(false)
            ;

            return $mock;
        });

        $this->assertFalse($correctionManager->hasCorrectionEditRight(get_class(new BlogArticle()), $user, $groupId));
    }


    protected function getCorrectionManager(callable $mockUserManagerCall = null)
    {
        $userManagerBuilder = $this->getMockBuilder('BNS\App\CoreBundle\User\BNSUserManager');
        $userManagerBuilder->disableOriginalConstructor();
        $userManagerMock = $userManagerBuilder->getMock();

        if ($mockUserManagerCall) {
            $userManagerMock = $mockUserManagerCall($userManagerMock);
        }

        return new CorrectionManager($userManagerMock, new NullLogger());
    }
}
