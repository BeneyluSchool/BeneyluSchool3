<?php

namespace BNS\App\HomeworkBundle\EventListener;

use BNS\App\HomeworkBundle\Model\Homework;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HomeworkDueSerializeSubscriber
 */
class HomeworkSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

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
                'class' => 'BNS\App\HomeworkBundle\Model\Homework',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var Homework $object */
        $object = $event->getObject();
        $visitor = $event->getVisitor();

        if ($object->getHasLocker()) {
            $locker = $this->container->get('bns.media_folder.locker_manager')->getLockerForHomework($object);
            if ($locker) {
                $visitor->addData('locker_marker', $locker->getMarker());
            }
        }
    }
}
