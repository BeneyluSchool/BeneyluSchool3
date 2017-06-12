<?php

namespace BNS\App\UserDirectoryBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class BaseUserDirectoryApiController
 */
class BaseUserDirectoryApiController extends BaseApiController
{

    /**
     * Checks access to the user directory
     *
     * @param string $view An optional view scope
     */
    protected function checkUserDirectoryAccess($view = null)
    {
        if ($view) {
            $permission = $this->get('bns.user_directory.manager')->getPermissionForView($view);
            $this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere($permission);
            return;
        }
        $this->get('bns.right_manager')->forbidIfHasNotRightSomeWhere('USER_DIRECTORY_ACCESS');
    }

    /**
     * Checks access to the given group
     *
     * @param  Group  $group
     * @param  string $view
     * @return bool
     * @throw AccessDeniedHttpException
     */
    protected function checkGroupAccess(Group $group, $view = null)
    {
        if (!$this->get('bns.user_directory.manager')->isVisibleUserDirectoryGroup($group, $view)) {
            throw new AccessDeniedHttpException("Invalid group " . $group->getType());
        }

        $groupsWhereAccess = $this->get('bns.user_directory.manager')->getGroupsWhereAccess($this->getUser(), $view);
        if (isset($groupsWhereAccess[$group->getId()])) {
            return true;
        } else {
            // No direct access, maybe it's a subgroup?
            $parent = $this->get('bns.group_manager')->setGroup($group)->getParent();
            $subgroupsWhereAccess = [];
            if ($parent) {
                $subgroupsWhereAccess = $this->get('bns.user_directory.manager')->getSubgroupsWhereAccess($parent, $this->getUser(), $view);
            }
            if (isset($subgroupsWhereAccess[$group->getId()])) {
                return true;
            }

            // No direct access, maybe it's from a partnership?
            $partnerships = $this->get('bns.partnership_manager')->getPartnershipsGroupBelongs($group->getId());
            foreach ($partnerships as $partnership) {
                if (isset($groupsWhereAccess[$partnership->getId()])) {
                    return true;
                }
            }
        }

        throw new AccessDeniedHttpException("No access to this group");
    }

    /**
     * Checks backend access to the given group
     *
     * @param Group $group
     */
    protected function checkGroupAccessBack(Group $group)
    {
        if (!$this->get('bns.user_directory.right_manager')->isGroupManageable($group)) {
            throw new AccessDeniedHttpException("No back access to this group");
        }
    }

}
