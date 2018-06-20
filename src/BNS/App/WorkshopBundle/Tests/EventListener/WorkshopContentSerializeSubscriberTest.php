<?php

namespace BNS\App\WorkshopBundle\Tests\EventListener;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\WorkshopBundle\EventListener\WorkshopContentSerializeSubscriber;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\EventDispatcher\PreSerializeEvent;
use JMS\Serializer\SerializationContext;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class WorkshopContentSerializeSubscriberTest extends AppWebTestCase
{
    public function testGetSubscribedEvents()
    {
        $this->assertEquals([
            [
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerialize',
                'class' => 'BNS\App\WorkshopBundle\Model\WorkshopContent',
            ],
        ], WorkshopContentSerializeSubscriber::getSubscribedEvents());
    }

    public function testOnPreSerializeNullObject()
    {
        $subscriber = new WorkshopContentSerializeSubscriber();
        $event = new ObjectEvent(new SerializationContext(), null, []);

        $this->assertNull($subscriber->onPostSerialize($event));
    }

    public function testOnPreSerializeWorkshopContentObject()
    {
        $subscriber = new WorkshopContentSerializeSubscriber();

        $workshopContent = new WorkshopContent();
        $workshopContent->setWorkshopDocument(new WorkshopDocument());
        $document = $workshopContent->getWorkshopDocument();
        $document->setStatus(WorkshopDocument::STATUS_LOCKED);

        $visitorMock = $this->getMockBuilder('JMS\Serializer\JsonSerializationVisitor')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $visitorMock
            ->expects($this->once())
            ->method('addData')
            ->with($this->equalTo('is_locked'), true)
        ;

        $contextMock = $this->getMockBuilder('JMS\Serializer\SerializationContext')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $contextMock
            ->expects($this->once())
            ->method('getVisitor')
            ->willReturn($visitorMock)
        ;

        $event = new ObjectEvent($contextMock, $workshopContent, []);
        $this->assertNull($subscriber->onPostSerialize($event));
    }

    public function testOnPreSerializeWorkshopContentObjectNotLocked()
    {
        $subscriber = new WorkshopContentSerializeSubscriber();

        $workshopContent = new WorkshopContent();
        $workshopContent->setWorkshopDocument(new WorkshopDocument());
        $document = $workshopContent->getWorkshopDocument();
        $document->setStatus(WorkshopDocument::STATUS_EDITABLE);

        $visitorMock = $this->getMockBuilder('JMS\Serializer\JsonSerializationVisitor')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $visitorMock
            ->expects($this->once())
            ->method('addData')
            ->with($this->equalTo('is_locked'), false)
        ;

        $contextMock = $this->getMockBuilder('JMS\Serializer\SerializationContext')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $contextMock
            ->expects($this->once())
            ->method('getVisitor')
            ->willReturn($visitorMock)
        ;

        $event = new ObjectEvent($contextMock, $workshopContent, []);
        $this->assertNull($subscriber->onPostSerialize($event));
    }
}
