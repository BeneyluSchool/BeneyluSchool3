<?php

namespace BNS\App\WorkshopBundle\EventListener;

use BNS\App\WorkshopBundle\Model\WorkshopContent;

use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;

/**
 * Class GroupSerializeSubscriber
 */
class WorkshopContentSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            array(
                'event' => 'serializer.post_serialize',
                'method' => 'onPostSerialize',
                'class' => 'BNS\App\WorkshopBundle\Model\WorkshopContent',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var WorkshopContent $content */
        $content = $event->getObject();

        if ($content && $content->isDocument()) {
            $isLocked = $content->getWorkshopDocument()->isLocked();

            $visitor = $event->getVisitor();
            $visitor->addData('is_locked', $isLocked);
        }
    }
}