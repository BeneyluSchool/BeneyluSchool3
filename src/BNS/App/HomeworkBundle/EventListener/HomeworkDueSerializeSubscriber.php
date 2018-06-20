<?php

namespace BNS\App\HomeworkBundle\EventListener;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\HomeworkBundle\Model\HomeworkDue;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Class HomeworkDueSerializeSubscriber
 */
class HomeworkDueSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    public function __construct(TokenStorageInterface $tokenStorage, BNSUserManager $userManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userManager = $userManager;
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
                'class' => 'BNS\App\HomeworkBundle\Model\HomeworkDue',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        /** @var HomeworkDue $object */
        $object = $event->getObject();
        $visitor = $event->getVisitor();

        if ($user = $this->getUser()) {
            $oldUser = $this->userManager->getUser();
            $this->userManager->setUser($user);
            if ($this->userManager->hasRightSomeWhere('HOMEWORK_SIGN')) {
                $visitor->addData('done', $object->isDoneBy($user));
            }
            if ($oldUser) {
                $this->userManager->setUser($oldUser);
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
