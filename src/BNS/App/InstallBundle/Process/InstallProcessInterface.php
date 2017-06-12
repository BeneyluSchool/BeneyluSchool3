<?php

namespace BNS\App\InstallBundle\Process;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface InstallProcessInterface
{
	/**
	 * Sets the install file name
	 *
	 * @param string $file
	 */
	public function setFile($file);

    /**
     * set the mode beta to auto generate beta ranks
     *
     * @param boolean $mode
     */
    public function setModeBeta($mode);

    /**
     * @deprecated use getUniqueName() instead
     * @param string $locale
     *
     * @return string The module name
     */
    public function getName($locale = 'fr');

    /**
     * @deprecated removed
     * @param string $locale
     *
     * @return string The module description
     */
    public function getDescription($locale = 'fr');

	/**
	 * @return string The module unique name
	 */
	public function getUniqueName();

	/**
	 * @return string The module type
	 */
	public function getType();

	/**
	 * @return array|string[] The module default ranks. Pupil, parent & other
	 */
	public function getDefaultRanks();

	/**
	 * @return bool The module contextable option
	 */
	public function isContextable();

	/**
	 * @param int $id
	 */
	public function setId($id);

	/**
	 * @return int The module id
	 */
	public function getId();

	/**
	 * @return array The module i18n options in array
	 */
	public function getI18ns();

	/**
	 * @return array|string[] The module permissions
	 */
	public function getPermissions();

	/**
	 * @return array|string[] The module ranks
	 */
	public function getRanks();

	/**
	 * @return string The bundle name without organisation prefix
	 */
	public function getBundleName();

    /**
     * @return array The notification types
     */
    public function getNotificationTypes();

    /**
     * @return array The markers
     */
    public function getMarkers();

	/**
	 * @return array
	 */
	public function getModuleInstallData();

	public function preModuleInstall();
	public function postModuleInstall();
	public function prePermissionInstall($permissionName, $permissionData);
	public function postPermissionInstall($permissionName, $permissionData);
	public function preRankInstall($rankName, $rankData);
	public function postRankInstall($rankName, $rankData);
	public function preNotificationTypeInstall($notificationTypeName, $notificationTypeData);
	public function postNotificationTypeInstall($notificationTypeName, $notificationTypeData);
    public function preMarkerInstall($markerName, $markerData);
	public function postMarkerInstall($markerName, $markerData);
}
