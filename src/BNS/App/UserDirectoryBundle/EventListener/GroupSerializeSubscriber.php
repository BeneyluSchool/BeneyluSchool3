<?php

namespace BNS\App\UserDirectoryBundle\EventListener;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\UserDirectoryBundle\Manager\GroupManager;
use BNS\App\UserDirectoryBundle\Manager\UserDirectoryRightManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class GroupSerializeSubscriber
 */
class GroupSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var GroupManager
     */
    private $groupManager;

    /**
     * @var UserDirectoryRightManager
     */
    private $rightManager;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(GroupManager $groupManager, UserDirectoryRightManager $rightManager, RouterInterface $router, ContainerInterface $container)
    {
        $this->groupManager = $groupManager;
        $this->rightManager = $rightManager;
        $this->router = $router;
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
                'class' => 'BNS\App\CoreBundle\Model\Group',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var Group $object */
        $object = $event->getObject();
        $parentId = $this->groupManager->getParentId($object);
        $isManageable = $this->rightManager->isGroupManageable($object, true);
        $isDistributable = $this->rightManager->isGroupDistributable($object);
        $canViewUsers = $this->rightManager->areGroupUsersVisible($object, $this->container->get('request')->get('view'));
        $partnerId = null;

        if ($isManageable) {
            if ('TEAM' === $object->getType()) {
                $isManageable = $this->router->generate('BNSAppClassroomBundle_back_team_details', array(
                    'slug' => $object->getSlug(),
                ), true);
            } else if ('PARTNERSHIP' === $object->getType()) {
                $partnerId = is_numeric($isManageable) ? $isManageable : null;
                $isManageable = $this->router->generate('BNSAppClassroomBundle_back_partnership_detail', array(
                    'partnershipSlug' => $object->getSlug(),
                ), true);
            }
        }

        $visitor = $event->getVisitor();
        $visitor->addData('parent_id', $parentId);
        if ($partnerId) {
            $visitor->addData('partner_id', $partnerId);
        }
        $visitor->addData('manageable', $isManageable);
        $visitor->addData('distributable', $isDistributable);
        $visitor->addData('view_users', $canViewUsers);
    }
}
