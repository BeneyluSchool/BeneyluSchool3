<?php
namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Events\ClearCacheEvent;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\EventDispatcher\Event;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiClearCacheListener
{
    protected $eventsOnTerminate = array(
        'USER' => array(),
        'GROUP' => array()
    );

    /**
     * @var BNSApi
     */
    protected $api;

    public function __construct(BNSApi $api)
    {
        $this->api = $api;
    }

    /**
     * Clear cache now or store event to be handle on kernel terminate
     *
     * @param ClearCacheEvent $event
     */
    public function onClearCache(ClearCacheEvent $event)
    {
        if ($event->isClearNow()) {
            $this->clearCache($event);
        } else {
            $this->eventsOnTerminate[$event->getObjectType()][$event->getObjectId()] = $event;
        }
    }

    /**
     * On kernel terminate clear api cache requested by event
     *
     * @param Event $event
     */
    public function onKernelTerminate(Event $event)
    {
        foreach ($this->eventsOnTerminate['USER'] as $event) {
            $this->clearCache($event);
        }
        foreach ($this->eventsOnTerminate['GROUP'] as $event) {
            $this->clearCache($event);
        }
    }

    protected function clearCache(ClearCacheEvent $event)
    {
        if (ClearCacheEvent::OBJECT_TYPE_USER === $event->getObjectType()) {
            $user = UserQuery::create()->findPk($event->getObjectId());
            if ($user) {
                $this->api->resetUser($user->getUsername());
            }
        }
        if (ClearCacheEvent::OBJECT_TYPE_GROUP === $event->getObjectType()) {
            $this->api->resetGroup($event->getObjectId(), false);
            $this->api->resetGroupUsers($event->getObjectId(), true);
        }
    }
}
