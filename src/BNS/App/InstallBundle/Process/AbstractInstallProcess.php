<?php

namespace BNS\App\InstallBundle\Process;

use \BNS\App\InstallBundle\Exception\InvalidFileConfiguration;

use \Symfony\Component\Yaml\Yaml;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractInstallProcess implements InstallProcessInterface
{

	/**
	 * Name of the install data file (without extension)
	 *
	 * @var string
	 */
	protected $file;

	/**
	 * Path to the directory of the install data file
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * The bundle containing this install process
	 *
	 * @var string
	 */
	protected $bundleName;

	/**
	 * @var array
	 */
	protected $installData;

	/**
	 * @var \Symfony\Component\HttpKernel\Bundle\Bundle
	 */
	protected $bundle;

	/**
	 * @var int
	 */
	protected $id;

    /**
     * @var boolean
     */
    protected $modeBeta = false;

	public function __construct()
	{
		// use a reflection class to get proper paths in child classes
		$reflection = new \ReflectionClass($this);
		$this->bundleName = $this->formatBundleName($reflection->getFileName());
		$this->directory = dirname($reflection->getFileName()) . '/../Resources/install';
		$this->file = 'install_data';
	}

	/**
	 * @param string $file
	 */
	public function setFile($file)
	{
		$this->file = $file;
	}

    /**
     * @param boolean $mode
     */
    public function setModeBeta($mode)
    {
        $this->modeBeta = (boolean) $mode;
    }

	/**
	 * @return array
	 *
	 * @throws InvalidFileConfiguration
	 */
	protected function getInstallData()
	{
		if (!isset($this->installData)) {
			$this->installData = Yaml::parse(file_get_contents($this->directory . '/' . $this->file . '.yml'));

			if (!is_array($this->installData)) {
				throw new \RuntimeException('The "Resources/data/install_data.yml" file is missing, please provide it before trying to install this module !');
			}
		}

		return $this->installData;
	}

	/**
	 * @return array<String>
	 *
	 * @throws InvalidFileConfiguration
	 */
	public function getModuleInstallData()
	{
		$installData = $this->getInstallData();
		if (!isset($installData['module'])) {
			throw new InvalidFileConfiguration('Please provide the "module" part in your install_data.yml file !');
		}

		return $installData['module'];
	}

    /**
     * @deprecated
     */
    public function getDescription($locale = 'fr')
    {
        return 'DESCRIPTION_' . $this->getUniqueName();
    }

    /**
     * @return array<String>
     *
     * @throws InvalidFileConfiguration
     */
    public function getDefaultRanks()
    {
        $moduleData = $this->getModuleInstallData();

        if (!isset($moduleData['default_rank']) && !isset($moduleData['default_rank_beta'])) {
            return null;
        }

        $finalDefaultRanks = array();

        if (isset($moduleData['default_rank'])) {
            $defaultRanks = $moduleData['default_rank'];

            foreach ($defaultRanks as $rank => $value) {
                if (null != $value) {
                    $finalDefaultRanks[$rank] = $this->getUniqueName() . '_' . $value;
                }
            }
        }

        if (isset($moduleData['default_rank_beta'])) {
            $defaultBetaRanks = $moduleData['default_rank_beta'];

            foreach ($defaultBetaRanks as $groupType => $roles) {
                foreach ($roles as $role => $rank) {
                    if (null != $rank) {
                        $finalDefaultRanks['beta'][$groupType][$role] = $this->getUniqueName() . '_' . $rank;
                    }
                }
            }
        }

        return $finalDefaultRanks;
    }

    /**
     * @deprecated use getUniqueName instead
     * @param string $locale
     *
     * @return string
     *
     * @throws InvalidFileConfiguration
     */
    public function getName($locale = 'fr')
    {
        return $this->getUniqueName();
    }

	/**
	 * @return string
	 *
	 * @throws InvalidFileConfiguration
	 */
	public function getUniqueName()
	{
		$moduleData = $this->getModuleInstallData();
		if (!isset($moduleData['unique_name'])) {
			throw new InvalidFileConfiguration('Please provide the "module:unique_name" part in your install_data.yml file !');
		}

		return $moduleData['unique_name'];
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		$moduleData = $this->getModuleInstallData();
		if (isset($moduleData['type'])) {
			return $moduleData['type'];
		}

		return 'APP';
	}

	/**
	 * @return boolean
	 */
	public function isContextable()
	{
		$moduleData = $this->getModuleInstallData();

		return isset($moduleData['is_contextable']) && $moduleData['is_contextable'] === true;
	}

	/**
     * @deprecated
     *
	 * @return int
	 *
	 * @throws InvalidFileConfiguration
	 */
	public function getI18ns()
	{
		$moduleData = $this->getModuleInstallData();
		if (!isset($moduleData['name'])) {
			throw new InvalidFileConfiguration('Please provide the "module:name" part in your install_data.yml file !');
		}

		return array_keys($moduleData['name']);
	}

	/**
	 * @return array<String>
	 *
	 * @throws InvalidFileConfiguration
	 */
	public function getPermissions()
	{
		$installData = $this->getInstallData();
		if (!isset($installData['permissions'])) {
			return array();
		}

		$permissions = $installData['permissions'];
		$finalPermissions = array();

		foreach ($permissions as $name => $data) {
			$finalPermissions[$this->getUniqueName() . '_' . $name] = $data;
		}

		// If extra permissions, add them without renaming
		if (isset($installData['extra_permissions'])) {
			$finalPermissions += $installData['extra_permissions'];
		}

		return $finalPermissions;
	}

	/**
	 * @return array<String>
	 *
	 * @throws InvalidFileConfiguration
	 */
	public function getRanks()
	{
		$installData = $this->getInstallData();
		if (!isset($installData['ranks'])) {
			return array();
		}

		$ranks = $installData['ranks'];
		$finalRanks = array();

		foreach ($ranks as $name => $data) {
			$finalPermissions = array();
			foreach ($data['permissions'] as $permission) {
				$finalPermissions[] = $this->getUniqueName() . '_' . $permission;
			}

			if (isset($data['extra_permissions'])) {
				$finalPermissions += $data['extra_permissions'];
			}

			$data['permissions'] = $finalPermissions;
			$finalRanks[$this->getUniqueName() . '_' . $name] = $data;
            if ($this->modeBeta) {
                $finalRanks[$this->getUniqueName() . '_' . $name . '_BETA'] = $data;
            }
		}

		return $finalRanks;
	}

    /**
     * @return array<String>
     */
    public function getNotificationTypes()
    {
        $installData = $this->getInstallData();
		if (!isset($installData['notification_types'])) {
			return array();
		}

        return $installData['notification_types'];
    }

    /**
     * @return array<String>
     */
    public function getMarkers()
    {
        $installData = $this->getInstallData();
		if (!isset($installData['markers'])) {
			return array();
		}

        return $installData['markers'];
    }

	/**
	 * @return string
	 */
	public function getBundleName()
	{
		return $this->bundleName;
	}

	/**
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return int
	 */
	public function getId()
	{
		return $this->id;
	}

	protected function formatBundleName($classpath)
	{
		// get path to parent directory (bundle)
		$bundlePath = realpath(dirname($classpath).'/../');

		// get last 3 components: vendor + app + bundle
		$parts = array_slice(explode(DIRECTORY_SEPARATOR, $bundlePath), -3, 3);

		return implode('', $parts);
	}

	/**
	 * Overrided methods
	 */
	public function preModuleInstall() { }
	public function postModuleInstall() { }
	public function prePermissionInstall($permissionName, $permissionData) { }
	public function postPermissionInstall($permissionName, $permissionData) { }
	public function preRankInstall($rankName, $rankData) { }
	public function postRankInstall($rankName, $rankData) { }
	public function preNotificationTypeInstall($notificationTypeName, $notificationTypeData) { }
	public function postNotificationTypeInstall($notificationTypeName, $notificationTypeData) { }
    public function preMarkerInstall($markerName, $markerData) { }
	public function postMarkerInstall($markerName, $markerData) { }
}
