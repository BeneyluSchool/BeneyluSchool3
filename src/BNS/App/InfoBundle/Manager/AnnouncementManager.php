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
     * @var array cache of permissions
     */
    protected $permissions;

    /**
     * @var BNSRightManager
     */
    protected $rightManager;

    public function __construct(BNSRightManager $rightManager)
    {
        $this->rightManager = $rightManager;
    }

    /**
     * @return \PropelObjectCollection|Announcement[]
     */
    public function getHomeAnnouncements()
    {
        return AnnouncementQuery::create()
            ->filterByActivated()
            ->filterByTypeHome()
            ->useI18nQuery($this->rightManager->getLocale(), null, \Criteria::INNER_JOIN)
                ->filterByLabel('', \Criteria::NOT_EQUAL)
            ->endUse()
            ->joinWithI18n($this->rightManager->getLocale(), \Criteria::INNER_JOIN)
            ->orderByCreatedAt(\Criteria::DESC)
            ->find()
        ;
    }

    /**
     * @param bool $count
     * @return int|array|\PropelObjectCollection|Announcement[]
     */
    public function getAnnouncements($count = false)
    {
        /** @var AnnouncementQuery $query */
        $query = AnnouncementQuery::create()
            ->filterByActivated()
            ->filterByTypeCustom()
            ->filterByPermissionUniqueName(null)
            ->_or()
            ->filterByPermissionUniqueName($this->getAllPermissions(), \Criteria::IN)
        ;
        if ($count) {
            return $query->count();
        }

        return $query
            ->joinWithI18n($this->rightManager->getLocale(), \Criteria::INNER_JOIN)
            ->orderByCreatedAt(\Criteria::DESC)
            ->find()
        ;
    }

    /**
     * @param bool $count
     * @return int|array|\PropelObjectCollection|AnnouncementUser[]
     */
    public function getReadUserAnnouncements($count = false)
    {
        /** @var AnnouncementUserQuery $query */
        $query = AnnouncementUserQuery::create()
            ->filterByUserId($this->rightManager->getUserSessionId())
            ->useAnnouncementQuery()
                ->filterByActivated()
                ->filterByTypeCustom()
                ->filterByParticipable(false)
                ->filterByPermissionUniqueName(null)
                ->_or()
                ->filterByPermissionUniqueName($this->getAllPermissions(), \Criteria::IN)
            ->endUse()
        ;
        if ($count) {
            return $query->count();
        }

        return $query->find();
    }

    /**
     * @param int $userId optional userId if null use current login user
     * @return int
     */
    public function countUnreadAnnouncements()
    {
        return $this->getAnnouncements(true) - $this->getReadUserAnnouncements(true);
    }

    protected function getAllPermissions()
    {
        if (null === $this->permissions) {
            $this->permissions = $this->rightManager->getAllPermissions();
        }

        return $this->permissions;
    }
}
