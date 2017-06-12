<?php
namespace BNS\App\CoreBundle\Right;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AssistantRightManager
{
    protected $modules = array();

    protected $permissions = array();

    protected $built = false;

    /**
     * @param array $permissions
     * @param array $modules
     */
    public function __construct(array $modules, array $permissions)
    {
        $this->modules = $modules;
        $this->permissions = $permissions;
    }

    /**
     * Check if assistant can use this $permission
     * @param $permission
     * @return bool return true if assistant can use $permission
     */
    public function canUsePermission($permission)
    {
        return false !== array_search($permission, $this->getAllowedPermissions(), true);
    }

    public function getAllowedModules()
    {
        return $this->modules;
    }

    public function getAllowedPermissions()
    {
        $this->buildPermissions();

        return $this->permissions;
    }

    /**
     * @param array $permissions
     * @return array permissions allowed for assistant
     */
    public function filterPermissions(array $permissions)
    {
        return array_intersect($permissions, $this->getAllowedPermissions());
    }

    /**
     * build permissions base on allowed module (add MODULE_ACCESS permission)
     */
    protected function buildPermissions()
    {
        if ($this->built) {
            return;
        }

        foreach ($this->modules as $module) {
            $permission = strtoupper($module) . '_ACCESS';

            if (false === array_search($permission, $this->permissions, true)) {
                $this->permissions[] = $permission;
            }
        }

        $this->built = true;
    }
}
