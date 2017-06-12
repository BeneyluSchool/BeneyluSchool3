<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use BNS\App\RealtimeBundle\Publisher\RealtimePublisher;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupLock;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupLockQuery;

/**
 * Class LockManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class LockManager
{

    /**
     * @var RealtimePublisher
     */
    private $publisher;

    public function __construct(RealtimePublisher $publisher)
    {
        $this->publisher = $publisher;
    }

    /**
     * Checks that the given User can lock the given WorkshopWidgetGroup
     *
     * @param User $user
     * @param WorkshopWidgetGroup $widgetGroup
     * @return bool
     */
    public function canLock(User $user, WorkshopWidgetGroup $widgetGroup)
    {
//        $currentUserLocksCount = WorkshopWidgetGroupLockQuery::create()
//            ->filterByUser($user)
//            ->filterByWorkshopDocumentId($widgetGroup->getWorkshopPage()->getDocumentId())
//            ->count()
//        ;
        $widgetGroupLocksCount = WorkshopWidgetGroupLockQuery::create()
            ->filterByWorkshopWidgetGroup($widgetGroup)
            ->count()
        ;

        return !$widgetGroupLocksCount;
    }

    /**
     * Creates a lock of the given User on the given WorkshopWidgetGroup
     *
     * @param User $user
     * @param WorkshopWidgetGroup $widgetGroup
     * @param boolean $notify
     * @return WorkshopWidgetGroupLock
     */
    public function lock(User $user, WorkshopWidgetGroup $widgetGroup, $notify = false)
    {
        $currentLocks = WorkshopWidgetGroupLockQuery::create()
            ->filterByUser($user)
            ->filterByWorkshopDocumentId($widgetGroup->getWorkshopPage()->getDocumentId())
            ->find()
        ;
        foreach ($currentLocks as $lock) {
            $this->delete($lock, $notify);
        }

        $lock = new WorkshopWidgetGroupLock();
        $lock->setUser($user)
            ->setWorkshopWidgetGroup($widgetGroup)
            ->setWorkshopDocumentId($widgetGroup->getWorkshopPage()->getDocumentId())
            ->save()
        ;

        if ($notify) {
            $this->publisher->publish('WorkshopDocument('.$widgetGroup->getWorkshopPage()->getDocumentId().'):locks:save', $lock);
        }

        return $lock;
    }

    public function getLock(User $user, WorkshopWidgetGroup $widgetGroup)
    {
        return WorkshopWidgetGroupLockQuery::create()
            ->filterByUser($user)
            ->filterByWorkshopWidgetGroup($widgetGroup)
            ->findOne()
        ;
    }

    /**
     * Removes a lock of the given User on the given WorkshopWidgetGroup
     *
     * @param User $user
     * @param WorkshopWidgetGroup $widgetGroup
     * @param bool $notify
     * @return WorkshopWidgetGroupLock|bool The lock if removed, else false
     */
    public function unlock(User $user, WorkshopWidgetGroup $widgetGroup, $notify = false)
    {
        $lock = WorkshopWidgetGroupLockQuery::create()
            ->filterByUser($user)
            ->filterByWorkshopWidgetGroup($widgetGroup)
            ->findOne()
        ;

        if ($lock) {
            $this->delete($lock, $notify);

            return $lock;
        } else {
            return false;
        }
    }

    /**
     * Gets the locks for the given User(s), optionally scoped to the given Workshop Document.
     *
     * @param mixed $user A user object or collection, or an id, or an array of ids
     * @param int $documentId
     * @return WorkshopWidgetGroupLock[]|\PropelObjectCollection
     */
    public function getUserLocks($user, $documentId = null)
    {
        return WorkshopWidgetGroupLockQuery::create()
            ->_if($user instanceof User || $user instanceof \PropelCollection)
                ->filterByUser($user)
            ->_else()
                ->filterByUserId($user)
            ->_endif()
            ->_if($documentId)
                ->filterByWorkshopDocumentId($documentId)
            ->_endif()
            ->find()
        ;
    }

    public function releaseAllLocks($userId, $notify = false)
    {
        $locks = $this->getUserLocks($userId);

        foreach ($locks as $lock) {
            $this->delete($lock, $notify);
        }
    }

    public function delete(WorkshopWidgetGroupLock $lock, $notify = false)
    {
        $lock->delete();

        // must notify before delete, to be able to populate all needed data
        if ($notify) {
            $this->publisher->publish('WorkshopDocument('.$lock->getWorkshopWidgetGroup()->getWorkshopPage()->getDocumentId().'):locks:remove', $lock, array('Default'));
        }
    }

}
