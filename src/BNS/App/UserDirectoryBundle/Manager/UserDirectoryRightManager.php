<?php

namespace BNS\App\UserDirectoryBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\Module;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\UserDirectoryBundle\Manager\UserDirectoryManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserDirectoryRightManager
 *
 * @package BNS\App\UserDirectoryBundle\Manager
 */
class UserDirectoryRightManager
{

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    /**
     * @var BNSRightManager
     */
    private $rightManager;

    public function __construct(ContainerInterface $container)
    {
        $this->groupManager = $container->get('bns.group_manager');
        $this->rightManager = $container->get('bns.right_manager');
    }

    /**
     * Checks if the given Group is manageable by the current User.
     *
     * @param Group $group
     * @return bool
     */
    public function isGroupManageable(Group $group, $returnPartnerId = false)
    {
        $permission = 'GROUP_ACCESS_BACK';
        switch ($group->getType()) {
            case 'SCHOOL':
            case 'CLASSROOM':
                $permission = $group->getType() . '_ACCESS_BACK';
                break;
            case 'TEAM':
                $parent = $this->groupManager->setGroup($group)->getParent();
                $permission = $this->isGroupManageable($parent);
                break;
            case 'PARTNERSHIP':
                foreach ($this->groupManager->setGroup($group)->getPartners() as $partner) {
                    if ($this->isGroupManageable($partner)) {
                        return $returnPartnerId ? $partner->getId() : true;
                    }
                }
                return false;
        }

        if (!$permission) {
            return false;
        }

        return $this->rightManager->hasRight($permission, $group->getId());
    }

    public function isGroupDistributable(Group $group)
    {
        return $this->rightManager->hasRight('CAMPAIGN_ACCESS', $group->getId()) || $this->rightManager->hasRight('PORTAL_ACCESS_BACK', $group->getId());
    }

    /**
     * Checks if users of the given group should be visible individually
     *
     * @param Group $group
     * @param string $view
     * @return bool
     */
    public function areGroupUsersVisible(Group $group, $view = null)
    {
        if (UserDirectoryManager::VIEW_CAMPAIGN_RECIPIENTS === $view) {
            return $this->rightManager->hasRight('CAMPAIGN_VIEW_INDIVIDUAL_USER', $this->rightManager->getCurrentGroupId());
        }

        return true;
    }

    /**
     * Gets a matrice of all modules for the given group, with the user roles and their activation state.
     *
     * For example:
     * [
     *   [
     *     'unique_name' => 'BLOG',
     *     'roles' => [ 'TEACHER' => true, 'PUPIL' => true, 'PARENT' => false ],
     *   ], [
     *     'unique_name' => 'FORUM',
     *     'roles' => [ 'TEACHER' => true, 'PUPIL' => true, 'PARENT' => true ],
     *   ]
     * ]
     *
     * @param Group $group
     * @return array
     */
    public function getModules(Group $group)
    {
        /** @var $activableModules Module[]|\PropelObjectCollection */
        switch ($group->getType()) {
            case 'PARTNERSHIP' :
                $activableModules = $this->rightManager->getActivablePartnershipModules();
                break;
            case 'TEAM' :
                $activableModules = $this->rightManager->getActivableModules($group->getId(), 'TEAM');
                break;
            default :
                // Hack to force module check outside of current context. "TEAM" is hardcoded everywhere...
                $activableModules = $this->rightManager->getActivableModules($group->getId(), 'TEAM');
                break;
        }

        $moduleStates = array();
        /** @var GroupType[] $roles */
        $roles = GroupTypeQuery::create()
            ->filterBySimulateRole(true)
            ->orderByType(\Criteria::DESC)
            ->findByType(array('TEACHER', 'PUPIL', 'PARENT'))
        ;
        $this->groupManager->setGroup($group);
        foreach ($roles as $role) {
            $moduleStates[$role->getType()] = $this->groupManager->getActivatedModules($role);
        }

        $modules = array();
        foreach ($activableModules as $module) {
            $m = array(
                'unique_name' => $module->getUniqueName(),
                'roles' => array(),
            );
            foreach ($moduleStates as $role => $activeModules) {
                $m['roles'][$role] = !!$activeModules->search($module);
            }

            if ($group->isPartnerShip() && 'MESSAGING' === $module->getUniqueName()) {
                unset ($m['roles']['PARENT']);
            }

            $modules[] = $m;
        }

        return $modules;
    }

}
