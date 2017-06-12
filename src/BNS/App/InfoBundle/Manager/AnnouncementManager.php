<?php

namespace BNS\App\InfoBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\InfoBundle\Model\Announcement;
use BNS\App\InfoBundle\Model\AnnouncementQuery;
use BNS\App\InfoBundle\Model\AnnouncementUser;
use BNS\App\InfoBundle\Model\AnnouncementUserQuery;

/**
 * Class AnnouncementManager
 *
 * @package BNS\App\InfoBundle\Manager
 */
class AnnouncementManager
{

    /**
     * @var BNSRightManager
     */
    protected $rightManager;

    public function __construct(BNSRightManager $rightManager)
    {
        $this->rightManager = $rightManager;
    }

    /**
     * @param bool $count
     * @return int|array|\PropelObjectCollection|Announcement[]
     */
    public function getAnnouncements($count = false)
    {
        $permissions = $this->rightManager->getAllPermissions();

        /** @var AnnouncementQuery $query */
        $query = AnnouncementQuery::create()
            ->joinWithI18n($this->rightManager->getLocale())
            ->filterByActivated()
            ->filterByTypeCustom()
            ->filterByParticipable(false)
            ->orderByCreatedAt(\Criteria::DESC)
            ->filterByPermissionUniqueName(null)
            ->_or()
            ->filterByPermissionUniqueName($permissions, \Criteria::IN)
        ;
        if ($count) {
            return $query->count();
        } else {
            return $query->find();
        }
    }

    /**
     * @param bool $count
     * @return int|array|\PropelObjectCollection|AnnouncementUser[]
     */
    public function getReadUserAnnouncements($count = false)
    {
        $permissions = $this->rightManager->getAllPermissions();

        /** @var AnnouncementUserQuery $query */
        $query = AnnouncementUserQuery::create()
            ->filterByUserId($this->rightManager->getUserSessionId())
            ->useAnnouncementQuery()
                ->filterByActivated()
                ->filterByTypeCustom()
                ->filterByParticipable(false)
                ->filterByPermissionUniqueName(null)
                ->_or()
                ->filterByPermissionUniqueName($permissions, \Criteria::IN)
            ->endUse()
        ;
        if ($count) {
            return $query->count();
        } else {
            return $query->find();
        }
    }

    /**
     * @return int
     */
    public function countUnreadAnnouncements()
    {
        return $this->getAnnouncements(true) - $this->getReadUserAnnouncements(true);
    }

}
