<?php

namespace BNS\App\InfoBundle\Model;

use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\InfoBundle\Model\om\BaseAnnouncement;

class Announcement extends BaseAnnouncement
{
    public $itemFeed;

    /**
     * @var Module
     */
    protected $module = null;

    /**
     * @var string
     */
    protected $moduleUniqueName = null;

    public function isParticipable()
    {
        return $this->getIsActive() && $this->getParticipable();
    }

    public function isReadable()
    {
        return $this->getType() != 'CUSTOM' || ($this->getIsActive() && $this->getType() == 'CUSTOM');
    }


    public function read(User $user)
    {
        AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->filterByParticipation(false)
            ->findOneOrCreate()
            ->save();
    }

    public function unread(User $user)
    {
        $announcement = AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->filterByParticipation(false)
            ->findOne();
        $announcement->delete();
    }

    public function participate(User $user)
    {
        $found = AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->findOneOrCreate();
        $found->setParticipation(true)->save();
    }

    public function unparticipate(User $user)
    {
        $announcement = AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->filterByParticipation(true)
            ->findOne();

        $announcement->setParticipation(false);
        $announcement->save();
    }

    public function isReadBy(User $user)
    {
        return AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->count() > 0;
    }

    public function isParticipateBy(User $user)
    {
        return AnnouncementUserQuery::create()
            ->filterByUser($user)
            ->filterByAnnouncement($this)
            ->filterByParticipation(true)
            ->count() > 0;
    }

    public function setItemFeed($itemFeed)
    {
        $this->itemFeed = $itemFeed;
    }

    public function getItemFeed()
    {
        return $this->itemFeed;
    }

    public function ensureI18nContent($locales)
    {
        $previousLocale = $this->getLocale();

        // collect all root languages, eg: en, fr, ...
        $rootLocales = [];
        foreach ($locales as $locale) {
            $rootLocales[] = substr($locale, 0, 2);  // en_US => en
        }
        $rootLocales = array_unique($rootLocales);

        // collect default values for these languages
        $defaultValues = [];
        foreach ($rootLocales as $rootLocale) {
            $this->setLocale($rootLocale);
            $defaultValues[$rootLocale] = [
                'label' => $this->getLabel(),
                'description' => $this->getDescription(),
            ];
        }

        // For each supported locale, ensure that all fields are filled.
        // For example, fields for 'en_US' will have defaults from the 'en' locale.
        foreach ($locales as $locale) {
            $rootLocale = substr($locale, 0, 2);
            if ($locale === $rootLocale) {
                continue;
            }

            $this->setLocale($locale);

            if (!$this->getLabel()) {
                $this->setLabel($defaultValues[$rootLocale]['label']);
            }
            if (!$this->getDescription()) {
                $this->setDescription($defaultValues[$rootLocale]['description']);
            }
        }

        $this->setLocale($previousLocale);
    }

    /**
     * @return null|string
     */
    public function getModuleUniqueName()
    {
        $module = $this->getModule();

        return $module ? $module->getUniqueName() : null;
    }

    /**
     * @return Module
     */
    public function getModule()
    {
        if (!$this->module && $this->getPermissionUniqueName()) {
            $permission = PermissionQuery::create()
                ->filterByUniqueName($this->getPermissionUniqueName())
                ->joinWith('Module')
                ->findOne();
            if ($permission) {
                $this->module = $permission->getModule();
            }
        }

        return $this->module;
    }

}
