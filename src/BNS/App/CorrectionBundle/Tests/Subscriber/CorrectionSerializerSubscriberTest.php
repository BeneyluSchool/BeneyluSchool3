<?php

namespace BNS\App\CorrectionBundle\Tests\Subscriber;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CorrectionBundle\Serializer\CorrectionSerilizerSubscriber;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\SerializationContext;
use Metadata\ClassMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionSerializerSubscriberTest extends AppWebTestCase
{
    public function testGetSubscribedEvents()
    {
        $this->assertEquals([
            [
                'event' => 'serializer.pre_serialize',
                'method' => 'onPreSerialize',
            ],
        ], CorrectionSerilizerSubscriber::getSubscribedEvents());
    }

    public function testOnPreSerializeNullObject()
    {


        $subscriber = $this->getCorrectionSerializerSubscriber($this->getNotCalled());
        $event = new PreSerializeEvent(new SerializationContext(), null, []);

        $this->assertNull($subscriber->onPreSerialize($event));
    }

    public function testOnPreSerializeStringObject()
    {
        $subscriber = $this->getCorrectionSerializerSubscriber($this->getNotCalled());

        $event = new PreSerializeEvent(new SerializationContext(), 'foo', []);

        $this->assertNull($subscriber->onPreSerialize($event));
    }

    public function testOnPreSerializeInvalidObject()
    {
        $subscriber = $this->getCorrectionSerializerSubscriber($this->getNotCalled());

        $event = new PreSerializeEvent(new SerializationContext(), new Blog(), []);

        $this->assertNull($subscriber->onPreSerialize($event));
    }

    public function testOnPreSerializeNoUser()
    {
        $subscriber = $this->getCorrectionSerializerSubscriber($this->getMethodCalled([
            'hasCorrectionRight' => 0,
            'hasCorrectionEditRight' => 0,
        ]));

        $event = new PreSerializeEvent(new SerializationContext(), new BlogArticle(), []);

        $this->assertNull($subscriber->onPreSerialize($event));
    }

    public function testOnPreSerialize()
    {
        $subscriber = $this->getCorrectionSerializerSubscriber($this->getMethodCalled([
            'hasCorrectionRight' => 1,
            'hasCorrectionEditRight' => 1,
        ]), new User());

        $event = new PreSerializeEvent(new SerializationContext(), new BlogArticle(), []);

        $this->assertNull($subscriber->onPreSerialize($event));
    }

    public function testOnPreSerializeWithRight()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $that = $this;
        $subscriber = $this->getCorrectionSerializerSubscriber(function($mock) use ($that) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            $mock->expects($that->once())
                ->method('hasCorrectionEditRight')
                ->willReturn(true)
            ;

            return $mock;
        }, new User());

        $metadataFactory = $container->get('jms_serializer.metadata_factory');
//        $classMetaData = new ClassMetadata('BNS\App\CoreBundle\Model\BlogArticle');
        $classMetaData = $metadataFactory->getMetadataForClass('BNS\App\WorkshopBundle\Model\WorkshopWidget');

        $contextBuilder = $this->getMockBuilder('JMS\Serializer\SerializationContext');
        $contextBuilder->disableOriginalConstructor();
        $contextMock = $contextBuilder->getMock();
        $contextMock
            ->expects($this->once())
            ->method('getMetadataFactory')
            ->will($this->returnValue($metadataFactory))
        ;
//        $contextMock
//            ->expects($this->once())
//            ->method('getMetadataForClass')
//            ->will($this->returnValue($classMetaData))
        ;

        $this->assertArrayNotHasKey('correction', $classMetaData->propertyMetadata);
        $count = count($classMetaData->propertyMetadata);

        $event = new PreSerializeEvent($contextMock, new WorkshopWidget(), []);

        $this->assertNull($subscriber->onPreSerialize($event));

        $this->assertCount($count + 1, $classMetaData->propertyMetadata);
        $this->assertArrayHasKey('correction', $classMetaData->propertyMetadata);
    }




    protected function getCorrectionSerializerSubscriber(callable $correctionManagerMockCallable = null, $user = null)
    {
        $correctionManagerBuilder = $this->getMockBuilder('BNS\App\CorrectionBundle\Manager\CorrectionManager');
        $correctionManagerBuilder->disableOriginalConstructor();
        $correctionManagerMock = $correctionManagerBuilder->getMock();

        if ($correctionManagerMockCallable) {
            $correctionManagerMock = $correctionManagerMockCallable($correctionManagerMock);
        }

        $tokenStorage = new TokenStorage();
        $tokenBuilder = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenMock = $tokenBuilder->getMock();
        $tokenMock->expects($this->atMost(1))
            ->method('getUser')
            ->willReturn($user)
        ;
        $tokenStorage->setToken($tokenMock);

        return new CorrectionSerilizerSubscriber($correctionManagerMock, $tokenStorage);
    }

    protected function getNotCalled()
    {
        return $this->getMethodCalled([
            'hasCorrectionRight' => 0,
            'hasCorrectionEditRight' => 0,
        ]);
    }

    /**
     * @param array $called [
     *  'method_name' => $numberOfCall or PHPUnit_Framework_MockObject_Matcher_Invocation
     * ]
     * @return \Closure
     */
    protected function getMethodCalled(array $called = [])
    {
        $that = $this;
        return function($mock) use ($that, $called) {
            /** @var $mock \PHPUnit_Framework_MockObject_MockObject */
            foreach ($called as $method => $number) {

                if ($number instanceof PHPUnit_Framework_MockObject_Matcher_Invocation) {
                    $matcher = $number;
                } elseif (0 === $number) {
                    $matcher = $that->never();
                } else {
                    $matcher = $that->exactly($number);
                }

                $mock->expects($matcher)
                    ->method($method)
                ;
            }

            return $mock;
        };
    }
}
