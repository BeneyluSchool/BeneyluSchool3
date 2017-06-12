<?php
namespace BNS\App\CoreBundle\Application;

use BNS\App\CoreBundle\Events\ApplicationUninstallEvent;
use BNS\App\CoreBundle\Events\BnsEvents;
use BNS\App\CoreBundle\Events\ClearCacheEvent;
use BNS\App\CoreBundle\Exception\InvalidApplication;
use BNS\App\CoreBundle\Exception\InvalidInstallApplication;
use BNS\App\CoreBundle\Exception\InvalidUninstallApplication;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupModuleQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\PermissionQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Handle installing / uninstalling of Application (BNS\App\CoreBundle\Model\Module)
 * Handle Application install state
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApplicationManager
{
    /**
     * @var bool
     */
    protected $enabled;

    /**
     * @var bool
     */
    protected $autoInstall;

    /**
     * @var bool
     */
    protected $uninstallDisabled;

    /**
     * @var array applications cache
     */
    protected $applications = array();

    /**
     * List of base applications (string unique name)
     *
     * @var array
     */
    protected $baseApplications;

    /**
     * List of system applications (allowed to have it, without installation)
     * @var array
     */
    protected $systemApplications;

    /**
     * List of private applications (string unique name)
     * @var array
     */
    protected $privateApplications;

    /**
     * @var Module[]|\PropelObjectCollection
     */
    protected $baseModules;

    /**
     * @var Module[]|\PropelObjectCollection
     */
    protected $systemModules;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * List of Module with authorized local :
     *
     * [ SPACE_OPS: ['fr'], LUNCH: ['fr', 'es', 'en_US']]
     * @var  array
     */
    protected $restrictedApplications;

    public function __construct(
        $enabled,
        $autoInstall,
        $uninstallDisabled,
        array $baseApplications = array(),
        array $systemApplications = array(),
        array $privateApplications = array(),
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
        array $restrictedApplications = array()
    )
    {
        $this->enabled = (bool) $enabled;
        $this->autoInstall = (bool) $autoInstall;
        $this->uninstallDisabled = (bool) $uninstallDisabled;
        $this->baseApplications = $baseApplications;
        $this->systemApplications = $systemApplications;
        $this->privateApplications = $privateApplications;
        $this->translator = $translator;
        $this->eventDispatcher = $eventDispatcher;
        $this->restrictedApplications = $restrictedApplications;
    }

    /**
     * Is application management enabled
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Disable Application management
     * for per project case
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * @return bool
     */
    public function isAutoInstall()
    {
        return $this->autoInstall;
    }

    /**
     * @return bool
     */
    public function isUninstallDisabled()
    {
        return $this->uninstallDisabled;
    }

    /**
     * @return array|\BNS\App\CoreBundle\Model\Module[]|mixed|\PropelObjectCollection
     */
    public function getBaseApplications($type = null)
    {
        // TODO optimize move this to configuration cache
        if (null === $this->baseModules || null !== $type) {
            $applications = $this->createModuleQuery()
                ->filterByUniqueName($this->baseApplications)
                ->_if($type)
                    ->filterByType($type, \Criteria::EQUAL)
                ->_endif()
                ->find()
                ;

            if (null !== $type) {
                return $applications;
            }

            $this->baseModules = $applications;
        }

        return $this->baseModules;
    }

    /**
     * @param Group $group
     * @return array|mixed|\PropelObjectCollection
     */
    public function getInstallableApplications(Group $group)
    {
        return $this->createModuleQuery()
            ->filterByUniqueName(array_merge(
                $this->baseApplications,
                $this->systemApplications,
                $this->getUserInstalledApplications($group)->toKeyValue('Id', 'UniqueName')
            ), \Criteria::NOT_IN)
            ->find()
        ;
    }

    /**
     * Return installed Application for a $group
     *
     * @param Group $group
     * @param array $userGroupRights the user's group's rights to filter application
     * @param string|null $type the type of application APP / EVENT
     * @param string $userLocale the locale of the user to restrict some apps
     * @return Module[]|\PropelObjectCollection
     */
    public function getInstalledApplications(Group $group, $userGroupRights = null, $type = null, $userLocale = null)
    {
        $modules = $this->getUserInstalledApplications($group, $userGroupRights, $type);
        $baseModules = $this->getBaseApplications($type);

        foreach ($baseModules as $baseModule) {
            if (null !== $type) {
                if ($baseModule->getType() !== $type) {
                    continue;
                }
            }
            if (!$this->isAllowed($baseModule, $group, $userLocale)) {
                continue;
            }
            if (!$modules->contains($baseModule)) {
                $modules->append($baseModule);
            }
        }

        return $modules;
    }

    /**
     * @param Group $group
     * @param array $userGroupRights the user's group's rights to filter application
     * @param null|string $type filter application type APP / EVENT, null means all
     * @return Module[]|array|mixed|\PropelObjectCollection
     * @throws \PropelException
     */
    public function getUserInstalledApplications(Group $group, $userGroupRights = null, $type = null)
    {
        $userInstalledModules = $this->createModuleQuery()
            ->_if($type)
                ->filterByType($type)
            ->_endif()
            ->useGroupModuleQuery()
                ->filterByGroup($group)
            ->endUse()
            ->find()
        ;

        // add auto installed modules
        if (($this->isAutoInstall() || !in_array($group->getType(), array('SCHOOL', 'CLASSROOM'))) && null !== $userGroupRights) {
            $modules = array();
            if (isset($userGroupRights['permissions']) && is_array($userGroupRights['permissions']))
            foreach ($userGroupRights['permissions'] as $permission) {
                if (preg_match('/^(.*)_ACCESS(_BACK)?$/', $permission, $matches)) {
                    if (isset($matches[1])) {
                        $moduleName = $matches[1];
                        if (in_array($moduleName, $this->baseApplications) || in_array($moduleName, $this->systemApplications)) {
                            continue;
                        }
                        $modules[$moduleName] = $moduleName;
                    }
                }
            }

            if (count($modules) > 0) {
                return $this->createModuleQuery()
                    ->_if($type)
                        ->filterByType($type)
                    ->_endif()
                    ->filterByUniqueName($modules, \Criteria::IN)
                    ->_or()
                    ->filterById($userInstalledModules->getPrimaryKeys(false), \Criteria::IN)
                    ->find()
                    ;
            }
        }

        return $userInstalledModules;
    }

    /**
     * return the dedicated module for the group
     * @param Group $group
     * @return Module
     */
    public function getGroupSpecialModule(Group $group)
    {
        $module = 'GROUP';
        switch ($group->getType()) {
            case 'CLASSROOM':
                $module = 'CLASSROOM';
                break;
            case 'SCHOOL':
                $module = 'SCHOOL';
                break;
            case 'TEAM':
                $module = 'TEAM';
                break;
        }

        $module = $this->createModuleQuery()->filterByUniqueName($module)->findOne();
        if ($module) {
            $module->setCustomLabel($group->getLabel());
        }
        // add type information to Module object
        if ('GROUP' === $module->getUniqueName()) {
            $module->groupType = $group->getType();
        }

        return $module;
    }

    /**
     * Return the list of permissions allowed for a $group
     *
     * @param Group $group
     * @return mixed|array list of allowed permissions (unique name)
     */
    public function getAllowedPermissions(Group $group, $userLocale = null)
    {
        $installedModules = $this->getUserInstalledApplications($group)->toKeyValue('Id', 'UniqueName');

        $allowedApplications = $this->filterAllowedApplications(array_merge(
            $this->systemApplications,
            $this->baseApplications,
            array_values($installedModules)
        ), $group, $userLocale);


        return PermissionQuery::create()
            ->useModuleQuery()
            // Module should be enabled
                ->filterByIsEnabled(true)
            //  allow system / base applications
                ->filterByUniqueName($allowedApplications)
            ->endUse()
            ->select(array('UniqueName'))
            ->find()
            ->getArrayCopy()
        ;
    }

    /**
     * Install an application for a group
     *
     * @param string $applicationName
     * @param Group $group
     * @throws \Exception
     * @throws \PropelException
     */
    public function installApplication($applicationName, Group $group)
    {
        // can't install base application
        if (in_array($applicationName, $this->baseApplications)) {
            throw new InvalidInstallApplication($applicationName, true);
        }

        // can't install system application
        if (in_array($applicationName, $this->systemApplications)) {
            throw new InvalidInstallApplication($applicationName, false);
        }

        // application is disabled or does not exist
        if (!($module = $this->getApplication($applicationName))) {
            throw new InvalidApplication($applicationName);
        }

        // only install if not already installed
        $installed = GroupModuleQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByModuleId($module->getId())
            ->findOneOrCreate()
            ;

        if ($installed->isNew()) {
            $installed->save();
        }

        // send event to clear group cache
        $this->eventDispatcher->dispatch(BnsEvents::CLEAR_CACHE, new ClearCacheEvent($group->getId(), ClearCacheEvent::OBJECT_TYPE_GROUP));
    }

    /**
     * Uninstall an application for a group
     *
     * @param string $applicationName
     * @param Group $group
     * @throws \Exception
     * @throws \PropelException
     */
    public function uninstallApplication($applicationName, Group $group)
    {
        // can't uninstall base application
        if (in_array($applicationName, $this->baseApplications)) {
            throw new InvalidUninstallApplication($applicationName, true);
        }

        // can't uninstall system application
        if (in_array($applicationName, $this->systemApplications) || !$this->canUninstall($applicationName)) {
            throw new InvalidUninstallApplication($applicationName, false);
        }

        // application is disabled or does not exist
        if (!($module = $this->getApplication($applicationName))) {
            throw new InvalidApplication($applicationName);
        }

        $moduleId = $module->getId();

        GroupModuleQuery::create()
            ->filterByGroup($group)
            ->filterByModule($module)
            ->delete()
        ;

        $group->removeFavoriteModule($moduleId);
        $group->save();

        // send event to notify of an uninstallation (for paas)
        $this->eventDispatcher->dispatch(BnsEvents::APPLICATION_UNINSTALL, new ApplicationUninstallEvent($module, $group));
        // send event to clear group cache
        $this->eventDispatcher->dispatch(BnsEvents::CLEAR_CACHE, new ClearCacheEvent($group->getId(), ClearCacheEvent::OBJECT_TYPE_GROUP));
    }

    /**
     * Is the application private (won't be shown to user with no rights)
     * @param $applicationName
     * @return bool
     */
    public function isPrivate($applicationName)
    {
        if (in_array($applicationName, $this->privateApplications)) {
            return true;
        }

        return false;
    }

    /**
     * Is application installable (not base application nor system application)
     * @param $applicationName
     * @return bool
     */
    public function canInstall($applicationName)
    {
        if (in_array($applicationName, $this->baseApplications) || in_array($applicationName, $this->systemApplications)) {
            return false;
        }

        // return bool if application exists
        return !!$this->getApplication($applicationName);
    }

    /**
     * Is application Uninstallable (not base application nor system application)
     * @param string $applicationName
     * @return bool
     */
    public function canUninstall($applicationName)
    {
        if ($this->uninstallDisabled) {
            return false;
        }

        return $this->canInstall($applicationName);
    }

    /**
     * @param string $applicationName
     * @return Module|null
     */
    public function getApplication($applicationName)
    {
        if (!isset($this->applications[$applicationName])) {
            $this->applications[$applicationName] = $this->createModuleQuery()
                ->filterByUniqueName($applicationName)
                ->findOne()
            ;
        }

        return $this->applications[$applicationName];
    }

    /**
     * @param Module $module
     * @param array $userGroupRights
     * @param array $activatedModules
     */
    public function decorate(Module $module, $userGroupRights, $activatedModules, Group $group = null)
    {
        $uniqueName = $module->getUniqueName();
        $module->isPrivate = $this->isPrivate($uniqueName);
        if ('GROUP' === $uniqueName || in_array($uniqueName . '_ACCESS', $userGroupRights['permissions'])) {
            $module->hasAccessFront = true;
        }
        if (in_array($uniqueName . '_ACCESS_BACK', $userGroupRights['permissions'])) {
            $module->hasAccessBack = true;
        }
        if (in_array($uniqueName . '_ACTIVATION', $userGroupRights['permissions'])) {
            $module->canOpen = true;
            $module->isUninstallable = $this->canUninstall($uniqueName);
        }
        if (isset($activatedModules[$uniqueName]) && null !== $activatedModules[$uniqueName]) {
            $module->isOpen = true;
            if (!$module->isPrivate && $module->canOpen && 'partial' === $activatedModules[$uniqueName]) {
                $module->isPartiallyOpen = true;
            }
        }

        if (!$module->isPrivate && !$module->hasAccessFront && !$module->hasAccessFront && $uniqueName === $module->getLabel()) {
            // HACK: Beta Mode Hack to hide apps invalid
            // TODO : find a real solution
            $module->isPrivate = true;
        }

        if ($group) {
            // add favorite / rank
            $module->isFavorite = $group->hasFavoriteModule($uniqueName);
            if (false !== $rank = array_search($uniqueName, $group->getSortedModules())) {
                $module->rank = $rank;
            }
        }

        $titleToken = 'META_TITLE_'.$module->getUniqueName();
        /** @Ignore */ $titleTranslated = $this->translator->trans($titleToken, [], 'MODULE');
        if ($titleTranslated !== $titleToken) {
            $module->metaTitle = $titleTranslated;
        }
    }

    /**
     * check if an application is allowed for a group. (Application NOT restricted or Application restricted by locale that match group locale)
     * @param Module $application
     * @param Group $group
     * @param string $userLocale the local of the user
     * @return bool true if the $application is allowed for $group
     */
    public function isAllowed(Module $application, Group $group, $userLocale = null)
    {
        return $this->isAllowedApplicationName($application->getUniqueName(), $group, $userLocale);
    }

    /**
     * Filter applications by group locale. Allow application NOT restricted or Application restricted by locale that match group locale
     * @param array $applicationNames
     * @param Group $group
     * @return array
     */
    public function filterAllowedApplications(array $applicationNames, Group $group, $userLocale = null)
    {
        $res = [];
        foreach ($applicationNames as $applicationName) {
            if ($this->isAllowedApplicationName($applicationName, $group, $userLocale)) {
                $res[] = $applicationName;
            }
        }

        return $res;
    }

    public function isAllowedApplicationName($applicationName, Group $group, $userLocale = null)
    {
        if (!isset($this->restrictedApplications[$applicationName])) {
            return true;
        }

        // Get lang of group to restrict apps, or user lang or (current locale might not be the right user)
        $lang = $group->getLang() ?: ($userLocale ?: $this->getLocale());

        return in_array($lang, $this->restrictedApplications[$applicationName], true);
    }

    protected function createModuleQuery()
    {
        return ModuleQuery::create()
            ->filterByIsEnabled(true)
            ;
    }

    protected function getLocale()
    {
        return $this->translator->getLocale();
    }
}
