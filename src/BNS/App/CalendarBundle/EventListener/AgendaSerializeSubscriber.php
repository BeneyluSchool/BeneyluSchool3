<?php

namespace BNS\App\CalendarBundle\EventListener;

use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

/**
 * Class WorkshopDocumentSerializeSubscriber
 */
class AgendaSerializeSubscriber implements EventSubscriberInterface
{

    /**
     * @var TokenStorage
     */
    private $tokenStorage;
    /**
     * @var BNSRightManager
     */
    private $rightManager;

    public function __construct(TokenStorage $tokenStorage, BNSRightManager $rightManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->rightManager = $rightManager;
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
                'class' => 'BNS\App\CoreBundle\Model\Agenda',
            )
        );
    }

    public function onPostSerialize(ObjectEvent $event)
    {
        $visitor = $event->getVisitor();
        // check that at least one of the serialization groups is present
        $groups = $event->getContext()->attributes->get('groups')->getOrElse([]);
        if (!count(array_intersect($groups, ['with_manageable']))) {
            return;
        }
        $user = $this->getUser();

        /** @var Agenda $agenda */
        $agenda = $event->getObject();
        if ($user->isChild()) {
            if(in_array($user->getId(), $agenda->getEditors()->getPrimaryKeys()) || $agenda->getUserId() === $user->getId()) {
                $visitor->addData('manageable', true);
            } else {
                $visitor->addData('manageable', false);
            }
        }
        elseif ($this->rightManager->hasRight('CALENDAR_ADMINISTRATION', $agenda->getGroupId()) || $agenda->getUserId() === $user->getId() || in_array($user->getId(), $agenda->getEditors()->getPrimaryKeys())) {
            $visitor->addData('manageable', true);
        }
        else {
            $visitor->addData('manageable', false);
        }
        // Add users editors
        $visitor->addData('users', $agenda->getEditorsIds());
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
