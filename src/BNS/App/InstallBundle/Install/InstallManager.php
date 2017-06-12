<?php

namespace BNS\App\InstallBundle\Install;

use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\Permission;
use BNS\App\CoreBundle\Model\PermissionQuery;
use BNS\App\CoreBundle\Model\RankDefaultQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Module\BNSModuleManager;
use BNS\App\InstallBundle\Exception\AuthModuleAlreadyInstalled;
use BNS\App\InstallBundle\Exception\AuthPermissionAlreadyInstalled;
use BNS\App\InstallBundle\Exception\AuthRankAlreadyInstalled;
use BNS\App\InstallBundle\Module\ModuleManagerInterface;
use BNS\App\InstallBundle\Process\InstallProcessInterface;
use BNS\App\NotificationBundle\Model\NotificationTypeQuery;
use BNS\App\YerbookBundle\Controller\FrontPaypalController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Kernel;
use \BNS\App\StatisticsBundle\Model\MarkerQuery;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class InstallManager
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var ModuleManagerInterface|BNSModuleManager
     */
    private $moduleManager;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var array<Module>
     */
    private $modules;

    /**
     * @var array|Permission[]
     */
    private $permissions;

    /**
     * @var array|\BNS\App\CoreBundle\Model\Rank[]
     */
    private $ranks;

    /**
     * @var array|\BNS\App\NotificationBundle\Model\NotificationType[]
     */
    private $notificationTypes;

    /**
     * @var array|\BNS\App\StatisticsBundle\Model\Marker[]
     */
    private $markers;

    /**
     * Holder for all install processes, unsorted
     *
     * @var InstallProcessInterface[]
     */
    private $installProcesses = array();

    /**
     * All install processes, indexed by module unique name
     *
     * @var InstallProcessInterface[]
     */
    private $availableProcessServices;

    /**
     * @var boolean
     */
    private $modeBeta;

    /**
     * @param Kernel $kernel
     * @param ModuleManagerInterface $moduleManager
     */
    public function __construct(Kernel $kernel, ModuleManagerInterface $moduleManager, TranslatorInterface $translator, $modeBeta)
    {
        $this->kernel = $kernel;
        $this->moduleManager = $moduleManager;
        $this->translator = $translator;
        $this->modeBeta = (boolean) $modeBeta;
    }

    /**
     * @param InstallProcessInterface $process
     */
    public function addProcessService(InstallProcessInterface $process, $file = null)
    {
        if ($file) {
            $process->setFile($file);
        }
        $process->setModeBeta($this->modeBeta);

        $this->installProcesses[] = $process;
    }

    /**
     * @return array|InstallProcessInterface[]
     */
    public function getAvailableProcessServices()
    {
        if (!isset($this->availableProcessServices)) {
            $this->availableProcessServices = array();

            foreach ($this->installProcesses as $process) {
                $this->availableProcessServices[$process->getUniqueName()] = $process;
            }
        }

        return $this->availableProcessServices;
    }

    public function getAvailableModuleNames()
    {
        $services = $this->getAvailableProcessServices();

        return array_keys($services);
    }

    /**
     * @param string $locale
     *
     * @return array|Module[]
     */
    protected function getInstalledModules($locale = 'fr')
    {
        if (!isset($this->modules)) {
            $this->refreshInstalledModules($locale);
        }

        return $this->modules;
    }

    /**
     * @param string $locale
     */
    protected function refreshInstalledModules($locale = 'fr')
    {
        $this->modules = ModuleQuery::create('m')
            ->orderBy('m.UniqueName')
            ->find();
    }

    /**
     * @return array Not installed modules
     */
    public function getNotInstalledModules()
    {
        $modules = $this->getInstalledModules();
        $availableModuleNames = $this->getAvailableModuleNames();
        $notInstalledModules = array();

        foreach ($availableModuleNames as $uniqueName) {
            $service = $this->getProcessService($uniqueName);
            $isFound = false;

            foreach ($modules as $module) {
                if ($module->getUniqueName() == $service->getUniqueName()) {
                    $isFound = true;
                    break;
                }
            }

            if (!$isFound) {
                $notInstalledModules[] = array(
                    'name' => $this->translator->trans($service->getName(), [], 'MODULE'),
                    'type' => $service->getType(),
                    'unique_name' => $service->getUniqueName(),
                    'description' => $this->translator->trans($service->getDescription(), [], 'MODULE'),
                );
            }
        }

        return $notInstalledModules;
    }

    /**
     * @param string $locale
     *
     * @return array|Module[] Installed AND enabled modules
     */
    public function getEnabledModules($locale = 'fr')
    {
        $modules = $this->getInstalledModules($locale);
        $enabledModules = array();

        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $enabledModules[] = $module;
            }
        }

        return $enabledModules;
    }

    /**
     * @param string $locale
     *
     * @return array|Module[] Installed but NOT enabled modules
     */
    public function getDisabledModules($locale = 'fr')
    {
        $modules = $this->getInstalledModules($locale);
        $disabledModules = array();

        foreach ($modules as $module) {
            if (!$module->isEnabled()) {
                $disabledModules[] = $module;
            }
        }

        return $disabledModules;
    }

    /**
     * @return Container
     */
    protected function getContainer()
    {
        return $this->kernel->getContainer();
    }

    /**
     * @param string $uniqueName
     *
     * @return \BNS\App\InstallBundle\Process\InstallProcessInterface
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function getProcessService($uniqueName)
    {
        $services = $this->getAvailableProcessServices();
        if (!isset($services[$uniqueName])) {
            throw new \RuntimeException(
                'The module "' . $uniqueName . '" is NOT available ! Please provide the install service before trying to install it.'
            );
        }

        $service = $services[$uniqueName];
        if (!$service instanceof InstallProcessInterface) {
            throw new \RuntimeException(
                'The install service of "' . $uniqueName . '" MUST implement the install process interface (InstallProcessInterface) !'
            );
        }

        return $service;
    }

    /**
     * @param string $moduleUniqueName
     *
     * @return Module
     *
     * @throws \InvalidArgumentException
     */
    public function getModule($moduleUniqueName)
    {
        $modules = $this->getInstalledModules();
        foreach ($modules as $module) {
            if ($module->getUniqueName() == $moduleUniqueName) {
                return $module;
            }
        }

        throw new \InvalidArgumentException('The module unique name ' . $moduleUniqueName . ' is NOT found !');
    }

    /**
     * @param string $moduleUniqueName
     *
     * @return boolean
     */
    protected function isInstalledModule($moduleUniqueName)
    {
        $modules = $this->getInstalledModules();
        foreach ($modules as $module) {
            if ($module->getUniqueName() == $moduleUniqueName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|Permission[]
     */
    protected function getPermissions()
    {
        if (!isset($this->permissions)) {
            $this->refreshPermissions();
        }

        return $this->permissions;
    }

    /**
     *
     */
    protected function refreshPermissions()
    {
        $this->permissions = PermissionQuery::create('p')->find();
    }

    /**
     * @param string $permissionUniqueName
     *
     * @return boolean
     */
    protected function isInstalledPermission($permissionUniqueName)
    {
        $permissions = $this->getPermissions();
        foreach ($permissions as $permission) {
            if ($permission->getUniqueName() == $permissionUniqueName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|\BNS\App\CoreBundle\Model\Rank[]
     */
    protected function getRanks()
    {
        if (!isset($this->ranks)) {
            $this->refreshRanks();
        }

        return $this->ranks;
    }

    /**
     *
     */
    protected function refreshRanks()
    {
        $this->ranks = RankQuery::create('r')->find();
    }

    /**
     * @param string $rankUniqueName
     *
     * @return boolean
     */
    protected function isInstalledRank($rankUniqueName)
    {
        $ranks = $this->getRanks();
        foreach ($ranks as $rank) {
            if ($rank->getUniqueName() == $rankUniqueName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|\BNS\App\NotificationBundle\Model\NotificationType[]
     */
    protected function getNotificationTypes()
    {
        if (!isset($this->notificationTypes)) {
            $this->refreshNotificationTypes();
        }

        return $this->notificationTypes;
    }

    /**
     *
     */
    protected function refreshNotificationTypes()
    {
        $this->notificationTypes = NotificationTypeQuery::create('nt')->find();
    }

    /**
     * @param string $notificationTypeName
     *
     * @return boolean
     */
    protected function isInstalledNotificationType($notificationTypeName)
    {
        $notificationTypes = $this->getNotificationTypes();
        foreach ($notificationTypes as $notificationType) {
            if ($notificationTypeName == $notificationType->getUniqueName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|\BNS\App\StatisticsBundle\Model\Marker[]
     */
    protected function getMarkers()
    {
        if (!isset($this->markers)) {
            $this->refreshmarkers();
        }

        return $this->markers;
    }

    /**
     *
     */
    protected function refreshMarkers()
    {
        $this->markers = MarkerQuery::create('m')->find();
    }

    /**
     * @param string $markerName
     *
     * @return boolean
     */
    protected function isInstalledMarker($markerName)
    {
        $markers = $this->getmarkers();
        foreach ($markers as $marker) {
            if ($marker == $marker->getUniqueName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ContainerAware $controller
     *
     * @return bool
     */
    public function isModuleEnabled($controller)
    {
        // Can be ExceptionController, RedirectController, ProfileController, ...
        if (!$controller instanceof Controller) {
            return true;
        }
        // temporary allow for payment in breakfast to work
        if ($controller instanceof FrontPaypalController) {
            return true;
        }

        $namespaces = explode('\\', get_class($controller));
        $bundleName = '';
        $count = count($namespaces);

        for ($i = 0; $i < $count - 2; $i++) {
            $bundleName .= $namespaces[$i];
        }

        $module = ModuleQuery::create('m')
            ->where('m.BundleName = ?', $bundleName)
            ->findOne();

        if (null == $module) {
            return true;
        }

        return $module->isEnabled();
    }

    /**
     * @param string $uniqueName
     * @param bool $isEnabled
     *
     * @throws \RuntimeException
     */
    public function install($uniqueName, $isEnabled = false)
    {
        $process = $this->getProcessService($uniqueName);

        // Install module
        $this->installModule($uniqueName, $process, $isEnabled);
        $this->refreshInstalledModules();

        // Install permissions
        foreach ($process->getPermissions() as $permissionName => $permissionData) {
            $this->installPermission($uniqueName, $permissionName, $process, $permissionData);
        }
        $this->refreshPermissions();

        // Install ranks
        foreach ($process->getRanks() as $rankName => $rankData) {
            $this->installRank($uniqueName, $rankName, $process, $rankData);
        }
        $this->refreshRanks();

        // Install notification types
        foreach ($process->getNotificationTypes() as $notificationTypeName => $notificationTypeData) {
            $this->installNotificationType($uniqueName, $notificationTypeName, $process, $notificationTypeData);
        }
        $this->refreshNotificationTypes();

        // Install markers of statistics
        foreach ($process->getMarkers() as $markerName => $markerData) {
            $this->installMarker($uniqueName, $markerName, $process, $markerData);
        }
        $this->refreshMarkers();
    }

    /**
     * @param string $uniqueName
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param bool $isEnabled
     *
     * @throws \RuntimeException
     */
    public function installModule($uniqueName, InstallProcessInterface $process = null, $isEnabled = false)
    {
        if (null == $process) {
            $process = $this->getProcessService($uniqueName);
        }

        try {
            if ($this->isInstalledModule($process->getUniqueName()) || $this->moduleManager->isInstalled($process)) {
                throw new \RuntimeException('The module "' . $uniqueName . '" is ALREADY installed !');
            }
        } catch (AuthModuleAlreadyInstalled $e) {
            $moduleResponse = $e->getModuleResponse();
            $process->setId($moduleResponse['id']);
        }

        // Install module object
        $process->preModuleInstall();
        $this->moduleManager->createFromProcess($process, $isEnabled);
        $process->postModuleInstall();
    }

    /**
     * @param string $uniqueName
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param string $permissionName
     * @param array $permissionData
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function installPermission($uniqueName, $permissionName, InstallProcessInterface $process = null, $permissionData = null)
    {
        if (null == $process) {
            $process = $this->getProcessService($uniqueName);
            if ($this->isInstalledModule($process->getUniqueName())) {
                $process->setId($this->getModule($process->getUniqueName())->getId());
            } else {
                throw new \RuntimeException('The module ' . $uniqueName . ' MUST be installed before installing his permissions !');
            }

            foreach ($process->getPermissions() as $pName => $pData) {
                if ($permissionName == $pName) {
                    $permissionData = $pData;
                    break;
                }
            }
        }

        if (!is_array($permissionData)) {
            $permissionData = array();
        }

        $alreadyInstalled = false;
        try {
            if ($this->isInstalledPermission($permissionName) || $this->moduleManager->isInstalledPermission($permissionName)) {
                throw new \RuntimeException('The permission "' . $permissionName . '" is ALREADY created !');
            }
        } catch (AuthPermissionAlreadyInstalled $e) {
            $alreadyInstalled = true;
        }

        $process->prePermissionInstall($permissionName, $permissionData);
        $this->moduleManager->createPermissionFromProcess($process, $permissionName, $permissionData, $alreadyInstalled);
        $process->postPermissionInstall($permissionName, $permissionData);
    }

    /**
     * @param string $uniqueName
     * @param string $rankName
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param array $rankData
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function installRank($uniqueName, $rankName, InstallProcessInterface $process = null, $rankData = null)
    {
        if (null == $process) {
            $process = $this->getProcessService($uniqueName);
            if ($this->isInstalledModule($process->getUniqueName())) {
                $process->setId($this->getModule($process->getUniqueName())->getId());
            } else {
                throw new \RuntimeException('The module ' . $uniqueName . ' MUST be installed before installing his ranks !');
            }

            foreach ($process->getRanks() as $rName => $rData) {
                if ($rankName == $rName) {
                    $rankData = $rData;
                    break;
                }
            }
        }

        if (null == $rankData) {
            throw new \InvalidArgumentException('The rank with name ' . $rankName . ' is NOT found for the module ' . $uniqueName . ' !');
        }

        // All permissions is installed ?
        foreach ($rankData['permissions'] as $permissionName) {
            if (!$this->isInstalledPermission($permissionName)) {
                throw new \RuntimeException(
                    'The permission "' . $permissionName . '" is NOT installed ! Please install the permission before installing the rank.'
                );
            }
        }

        $alreadyInstalled = false;
        try {
            if ($this->isInstalledRank($rankName) || $this->moduleManager->isInstalledRank($rankName)) {
                throw new \RuntimeException('The rank "' . $rankName . '" is ALREADY created !');
            }
        } catch (AuthRankAlreadyInstalled $e) {
            $alreadyInstalled = true;
        }

        $process->preRankInstall($rankName, $rankData);
        $this->moduleManager->createRankFromProcess($process, $rankName, $rankData, $alreadyInstalled);
        $process->postRankInstall($rankName, $rankData);
    }

    /**
     * @param string $uniqueName
     * @param string $notificationTypeName
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param array $notificationTypeData
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function installNotificationType($uniqueName, $notificationTypeName, InstallProcessInterface $process = null, $notificationTypeData = null)
    {
        $found = true;
        if (null == $process) {
            $found = false;
            $process = $this->getProcessService($uniqueName);

            if ($this->isInstalledModule($process->getUniqueName())) {
                $process->setId($this->getModule($process->getUniqueName())->getId());
            } else {
                throw new \RuntimeException('The module ' . $uniqueName . ' MUST be installed before installing his notification types !');
            }

            foreach ($process->getNotificationTypes() as $ntName => $ntData) {
                if ($notificationTypeName == $ntName) {
                    $notificationTypeData = $ntData;
                    $found = true;
                    break;
                }
            }
        }

        if (!$found && null == $notificationTypeData) {
            throw new \InvalidArgumentException(
                'The notification type with name ' . $notificationTypeName . ' is NOT found for the module ' . $uniqueName . ' !'
            );
        }

        if (null == $notificationTypeData) {
            $notificationTypeData = array();
        }

        $process->preNotificationTypeInstall($notificationTypeName, $notificationTypeData);
        $this->moduleManager->createNotificationTypeFromProcess($process, $notificationTypeName, $notificationTypeData);
        $process->postNotificationTypeInstall($notificationTypeName, $notificationTypeData);
    }

    /**
     * @param string $uniqueName
     * @param string $markerName
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param array $markerData
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function installMarker($uniqueName, $markerName, InstallProcessInterface $process = null, $markerData = null)
    {
        $found = true;
        if (null == $process) {
            $found = false;
            $process = $this->getProcessService($uniqueName);

            if ($this->isInstalledModule($process->getUniqueName())) {
                $process->setId($this->getModule($process->getUniqueName())->getId());
            } else {
                throw new \RuntimeException('The module ' . $uniqueName . ' MUST be installed before installing his statistics markers !');
            }

            foreach ($process->getMarkers() as $mName => $mData) {
                if ($markerName == $mName) {
                    $markerData = $mData;
                    $found = true;
                    break;
                }
            }
        }

        if (!$found && null == $markerData) {
            throw new \InvalidArgumentException('The marker with name ' . $markerName . ' is NOT found for the module ' . $uniqueName . ' !');
        }

        if (null == $markerData) {
            $markerData = array();
        }

        $process->preMarkerInstall($markerName, $markerData);
        $this->moduleManager->createMarkerFromProcess($process, $markerName, $markerData);
        $process->postMarkerInstall($markerName, $markerData);
    }

    /**
     * Dump all the install process : installed module & permissions and where (app, auth)
     *
     * @param string $uniqueName
     *
     * @return array
     */
    public function dumpProcess($uniqueName)
    {
        $process = $this->getProcessService($uniqueName);
        $dump = array(
            'type' => $process->getType(),
            'module' => array(),
            'permissions' => array()
        );

        // Module APP
        if ($this->isInstalledModule($process->getUniqueName())) {
            $dump['module']['APP'] = true;
        } else {
            $dump['module']['APP'] = false;
        }

        // Module AUTH
        try {
            if (!$this->moduleManager->isInstalled($process)) {
                $dump['module']['AUTH'] = false;
            }
        } catch (AuthModuleAlreadyInstalled $e) {
            $dump['module']['AUTH'] = true;
        }

        // Permissions
        foreach ($process->getPermissions() as $permissionName => $permissionData) {
            $dump['permissions'][$permissionName] = array();

            // Permission APP
            if ($this->isInstalledPermission($permissionName)) {
                $dump['permissions'][$permissionName]['APP'] = true;
            } else {
                $dump['permissions'][$permissionName]['APP'] = false;
            }

            // Permission AUTH
            try {
                if (!$this->moduleManager->isInstalledPermission($permissionName)) {
                    $dump['permissions'][$permissionName]['AUTH'] = false;
                }
            } catch (AuthPermissionAlreadyInstalled $e) {
                $dump['permissions'][$permissionName]['AUTH'] = true;
            }
        }

        // Ranks
        foreach ($process->getRanks() as $rankName => $rankData) {
            $dump['ranks'][$rankName] = array();

            // Rank APP
            if ($this->isInstalledRank($rankName)) {
                $dump['ranks'][$rankName]['APP'] = true;
            } else {
                $dump['ranks'][$rankName]['APP'] = false;
            }

            // Rank AUTH
            try {
                if (!$this->moduleManager->isInstalledRank($rankName)) {
                    $dump['ranks'][$rankName]['AUTH'] = false;
                }
            } catch (AuthRankAlreadyInstalled $e) {
                $dump['ranks'][$rankName]['AUTH'] = true;
            }
        }

        // default ranks
        if ($dump['module']['APP']) {
            $invalidDefaultRank = $this->moduleManager->getInvalidDefaultRank($process);
            $defaultRanks = $process->getDefaultRanks();
            if ($defaultRanks) {
                foreach ($defaultRanks as $role => $value) {
                    if ('beta' === $role) {
                        continue;
                    }
                    $dump['default_ranks'][$role] = [
                        'NAME' => $value,
                        'APP' => !in_array($role, $invalidDefaultRank)
                    ];
                }

                if (isset($defaultRanks['beta'])) {
                    foreach ($defaultRanks['beta'] as $groupType => $roles) {
                        foreach ($roles as $role => $value) {
                            $dump['default_beta_ranks'][$groupType][$role] = [
                                'NAME' => $value,
                                'APP' => RankDefaultQuery::create()
                                        ->filterByBeta(true)
                                        ->filterByGroupType($groupType)
                                        ->filterByRole($role)
                                        ->filterByRankDefault($value)
                                        ->count() > 0
                            ];
                        }
                    }
                }
            }
        }

        // Notification types
        foreach ($process->getNotificationTypes() as $notificationTypeName => $notificationData) {
            $dump['notification_types'][$notificationTypeName] = array();

            if ($this->isInstalledNotificationType($notificationTypeName)) {
                $dump['notification_types'][$notificationTypeName]['APP'] = true;
            } else {
                $dump['notification_types'][$notificationTypeName]['APP'] = false;
            }
        }

        // Statistics markers
        foreach ($process->getMarkers() as $markerName => $markerData) {
            $dump['markers'][$markerName] = array();

            if ($this->isInstalledMarker($markerName)) {
                $dump['markers'][$markerName]['APP'] = true;
            } else {
                $dump['markers'][$markerName]['APP'] = false;
            }
        }

        return $dump;
    }

    /**
     * @param $uniqueName
     * @throws \Exception
     * @throws \PropelException
     */
    public function setDefaultRanks($uniqueName)
    {
        $process = $this->getProcessService($uniqueName);
        $module = $this->getModule($uniqueName);
        $this->moduleManager->setDefaultRanks($process, $module);
        $module->save();
    }

    /**
     * @param $uniqueName
     * @throws \Exception
     * @throws \PropelException
     */
    public function setDefaultBetaRanks($uniqueName)
    {
        $process = $this->getProcessService($uniqueName);
        $module = $this->getModule($uniqueName);
        $this->moduleManager->setDefaultBetaRanks($process, $module);
        $module->save();
    }
    
    /**
     * @param string $bundleName
     *
     * @return string
     */
    public static function serviceFormat($bundleName)
    {
        return 'install_process_' . Container::underscore($bundleName);
    }
}
