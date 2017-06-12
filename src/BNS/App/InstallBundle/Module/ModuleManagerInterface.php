<?php

namespace BNS\App\InstallBundle\Module;

use \BNS\App\InstallBundle\Process\InstallProcessInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface ModuleManagerInterface
{
	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 *
	 * @return bool True if the module is installed on auth
	 */
	public function isInstalled(InstallProcessInterface $process);

	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 * @param boolean $isEnabled
	 *
	 * @return \BNS\App\CoreBundle\Model\Module The module
	 */
	public function createFromProcess(InstallProcessInterface $process, $isEnabled);

	/**
	 * @param string $permissionName
	 *
	 * @return bool True if the permission is installed on auth
	 */
	public function isInstalledPermission($permissionName);

	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 * @param string  $permissionName
	 * @param array   $permissionData
	 * @param boolean $alreadyInstalled
	 *
	 * @return \BNS\App\CoreBundle\Model\Permission The permission
	 */
	public function createPermissionFromProcess(InstallProcessInterface $process, $permissionName, array $permissionData, $alreadyInstalled = false);

	/**
	 * @param string $rankName
	 *
	 * @return bool True if the rank is installed on auth
	 */
	public function isInstalledRank($rankName);

	/**
	 * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
	 * @param string  $rankName
	 * @param array   $rankData
	 * @param boolean $alreadyInstalled
	 *
	 * @return \BNS\App\CoreBundle\Model\Rank The rank
	 */
	public function createRankFromProcess(InstallProcessInterface $process, $rankName, array $rankData, $alreadyInstalled = false);

    /**
     * 
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param string $markerName
     * @param array $markerData
     * 
     * @return \BNS\App\StatisticsBundle\Model\Marker the marker
     */
	public function createMarkerFromProcess(InstallProcessInterface $process, $markerName, array $markerData);
    
    /**
     * @param \BNS\App\InstallBundle\Process\InstallProcessInterface $process
     * @param string $notificationTypeName
     * @param array $notificationTypeData
     *
     * @return \BNS\App\NotificationBundle\Model\NotificationType The notification type
     */
	public function createNotificationTypeFromProcess(InstallProcessInterface $process, $notificationTypeName, array $notificationTypeData);
}
