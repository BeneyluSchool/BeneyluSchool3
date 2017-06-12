<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RightManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class RightManager
{

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets the IDs of users of whom the given User can manage workshop content.
     *
     * @param User $user
     * @return array
     */
    public function getManagedAuthorIds(User $user)
    {
        $authorIds = array();
        $groupsWithWorkshopAdmin = $this->container->get('bns.user_manager')->setUser($user)->getGroupsWherePermission('WORKSHOP_ACTIVATION');
        foreach ($groupsWithWorkshopAdmin as $group) {
            $groupAuthorIds = array_keys($this->container->get('bns.group_manager')->setGroup($group)->getUsersByPermissionUniqueName('WORKSHOP_ACCESS'));
            $authorIds = array_merge($authorIds, $groupAuthorIds);
        }

        return array_unique($authorIds);
    }

}
