<?php
namespace BNS\App\CoreBundle\Events;

use Symfony\Component\EventDispatcher\Event;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ClearCacheEvent extends Event
{
    const OBJECT_TYPE_GROUP = 'GROUP';
    const OBJECT_TYPE_USER  = 'USER';

    /**
     * @var int
     */
    protected $objectId;

    /**
     * @var string GROUP|USER
     */
    protected $objectType;

    protected $clearNow = false;

    /**
     * @param $objectId
     * @param $objectType
     * @param bool|true $clearNow whether you want the cache to be cleared now or onTerminate event
     */
    public function __construct($objectId, $objectType, $clearNow = false)
    {
        if (!in_array($objectType, array(self::OBJECT_TYPE_GROUP, self::OBJECT_TYPE_USER))) {
            throw new \InvalidArgumentException('invalid object type');
        }

        $this->objectType = $objectType;
        $this->objectId = $objectId;
        $this->clearNow = $clearNow;
    }

    /**
     * @return int
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @return string
     */
    public function getObjectType()
    {
        return $this->objectType;
    }

    /**
     * @return boolean
     */
    public function isClearNow()
    {
        return $this->clearNow;
    }
}
