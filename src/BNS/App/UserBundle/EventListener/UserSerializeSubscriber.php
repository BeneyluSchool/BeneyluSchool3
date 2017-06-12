<?php

namespace BNS\App\UserBundle\EventListener;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class GroupSerializeSubscriber
 */
class UserSerializeSubscriber implements EventSubscriberInterface
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
                'class' => 'BNS\App\CoreBundle\Model\User',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['detail', 'user_detail']))) {
            return;
        }

        /** @var User $object */
         $object = $event->getObject();
        $rights = array(
            'workshop_document_manage_lock' => $this->container->get('bns.right_manager')->hasRightSomeWhere('WORKSHOP_ACTIVATION'),
            'breakfast_tour_activation' => $this->container->get('bns.right_manager')->hasRight('BREAKFAST_TOUR_ACTIVATION'),
            'space_ops_activation' => $this->container->get('bns.right_manager')->hasRight('SPACE_OPS_ACTIVATION'),
            'workshop_questionnaire_widgets_use' => $this->container->get('bns.right_manager')->hasRight('WORKSHOP_QUESTIONNAIRE_WIDGETS_USE'),
            'workshop_questionnaire_create' => $this->container->get('bns.right_manager')->hasRight('WORKSHOP_QUESTIONNAIRE_CREATE'),
            'school_competition_manage' => $this->container->get('bns.right_manager')->hasRight('SCHOOL_COMPETITION_MANAGE'),
        );

        $highRoleId = $object->getHighRoleId();
        if ($highRoleId) {
            $mainRole = strtolower($this->container->get('bns.role_manager')->getGroupTypeRoleFromId($highRoleId)->getType());
        } else {
            $mainRole = 'UNKNOWN';
        }

        $visitor = $event->getVisitor();
        $visitor->addData('rights', $rights);
        $visitor->addData('main_role', $mainRole);
    }
}
