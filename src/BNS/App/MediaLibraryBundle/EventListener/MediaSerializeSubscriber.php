<?php

namespace BNS\App\MediaLibraryBundle\EventListener;

use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class WorkshopDocumentSerializeSubscriber
 */
class MediaSerializeSubscriber implements EventSubscriberInterface
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
                'class' => 'BNS\App\MediaLibraryBundle\Model\Media',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['detail', 'media_detail']))) {
            return;
        }

        $rights = array();

        /** @var Media $media */
        $media = $event->getObject();
        if ($media->isWorkshopDocument()) {
            $content = $media->getWorkshopContents()->getFirst();
            if ($content) {
                $user = $this->container->get('security.context')->getToken()->getUser();
                $rights['workshop_document_edit'] = $this->container->get('bns.workshop.content.manager')->canManage($content, $user);
            }
        }

        $visitor = $event->getVisitor();
        $visitor->addData('rights', $rights);
    }
}
