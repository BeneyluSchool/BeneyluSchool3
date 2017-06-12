<?php

namespace BNS\App\ResourceBundle\Subscriber;

use BNS\App\ResourceBundle\BNSResourceManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * Class ResourceSerializationSubscriber
 *
 * @package BNS\App\ResourceBundle\Subscriber
 */
class ResourceSerializationSubscriber implements EventSubscriberInterface
{

    /**
     * @var BNSResourceManager
     */
    private $resourceManager;

    /**
     * @var Router
     */
    private $router;

    /**
     * Returns the events to which this class has subscribed.
     *
     * Return format:
     *     array(
     *         array('event' => 'the-event-name', 'method' => 'onEventName', 'class' => 'some-class', 'format' => 'json'),
     *         array(...),
     *     )
     *
     * The class may be omitted if the class wants to subscribe to events of all classes.
     * Same goes for the format key.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            array('event' => 'serializer.post_serialize', 'method' => 'onPostSerialize', 'class' => 'BNS\App\ResourceBundle\Model\Resource'),
        );
    }

    public function __construct(BNSResourceManager $resourceManager, Router $router)
    {
        $this->resourceManager = $resourceManager;
        $this->router = $router;
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var \BNS\App\ResourceBundle\Model\Resource $resource */
        $resource = $event->getObject();

        // generate a public url for the resource
        $url = $this->router->generate('resource_visualize_public_document', array(
            'resourceSlug' => $resource->getSlug(),
            'hash' => $this->resourceManager->generatePublicHash($resource),
            'size' => 'original',
        ), true);

        // add it to the serialized data
        $event->getVisitor()->addData('url', $url);
    }

}
