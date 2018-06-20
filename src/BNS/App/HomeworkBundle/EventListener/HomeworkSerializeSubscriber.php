<?php

namespace BNS\App\HomeworkBundle\EventListener;

use BNS\App\CoreBundle\Model\User;
use BNS\App\HomeworkBundle\Homework\HomeworkRightManager;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderLockerManager;
use BNS\App\WorkshopBundle\Manager\LockManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use JMS\Serializer\JsonSerializationVisitor;
use JMS\Serializer\VisitorInterface;
use PhpOption\Option;
use PhpOption\Some;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class HomeworkDueSerializeSubscriber
 */
class HomeworkSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var MediaFolderLockerManager
     */
    protected $lockerManager;

    /**
     * @var HomeworkRightManager
     */
    protected $homeworkRightManager;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(MediaFolderLockerManager $lockerManager, HomeworkRightManager $homeworkRightManager, TokenStorageInterface $tokenStorage)
    {
        $this->lockerManager = $lockerManager;
        $this->homeworkRightManager = $homeworkRightManager;
        $this->tokenStorage = $tokenStorage;
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
        /** @var JsonSerializationVisitor $visitor */
        $visitor = $event->getVisitor();

        $groups = [];
        $option = $event->getContext()->attributes->get('groups');
        if ($option instanceof Some) {
            $groups = (array)$option->get();
        }

        if ($object->getHasLocker()) {
            $locker = $this->lockerManager->getLockerForHomework($object);
            if ($locker) {
                $visitor->addData('locker_marker', $locker->getMarker());
            }
        }

        if ($user = $this->getUser()) {
            if (in_array('homework_groups', $groups)) {
                $allowedGroups = $this->homeworkRightManager->getHomeworkGroups($object, $user);
                $data = $visitor->visitArray($allowedGroups, ['BNS\App\CoreBundle\Model\Group'], $event->getContext());
                $visitor->addData('groups', $data);
            }
            if (in_array('homework_users', $groups)) {
                $allowedUsers = $this->homeworkRightManager->getHomeworkUsers($object, $user);
                $visitor->addData('users', $visitor->visitArray($allowedUsers, ['BNS\App\CoreBundle\Model\User'], $event->getContext()));
            }

            if (in_array('homework_children', $groups)) {
                $allowedChildren = $this->homeworkRightManager->getHomeworkChildren($object, $user);
                $visitor->addData('children', $visitor->visitArray($allowedChildren, ['BNS\App\CoreBundle\Model\User'], $event->getContext()));
            }
        }
    }

    protected function getUser()
    {
        if ($token = $this->tokenStorage->getToken()) {
            $user = $token->getUser();
            if ($user && $user instanceof User) {
                return $user;
            }
        }

        return null;
    }
}
