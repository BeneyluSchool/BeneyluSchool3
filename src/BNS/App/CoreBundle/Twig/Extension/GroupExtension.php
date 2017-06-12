<?php
namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

class GroupExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'nb_users' => new Twig_Function_Method($this, 'nbUsers', array()),
            'last_connected' => new Twig_Function_Method($this, 'lastConnected', array()),
            'current_group' => new Twig_Function_Method($this, 'currentGroup', array()),
            'current_group_id' => new Twig_Function_Method($this, 'currentGroupId', array()),
            'current_group_type' => new Twig_Function_Method($this, 'currentGroupType', array())
        );
    }

	/**
	 * @param \BNS\App\CoreBundle\Twig\Extension\User $user
	 * 
	 * @return boolean
	 */
    public function nbUsers(Group $group, $roleUniqueName = null)
    {
		$gm = $this->container->get('bns.group_manager')->setGroup($group);
        return $gm->getNbUsers($roleUniqueName);
    }

    /**
     * @param Group $group
     * @param int $limit
     * @return array|User
     */
    public function lastConnected(Group $group, $limit = 1)
    {
        $groupManager = $this->container->get('bns.group_manager');
        $groupManager->setGroup($group);

        $lastConnected = $groupManager->getLastUsersConnected($limit);

        if (1 == $limit) {
            return $lastConnected->getFirst();
        }

        return $lastConnected;
    }

    /**
     * @param \BNS\App\CoreBundle\Twig\Extension\User $user
     *
     * @return boolean
     */
    public function currentGroupType()
    {
        return $this->container->get('bns.right_manager')->getCurrentGroup()->getGroupType()->getType();
    }

    public function currentGroupId()
    {
        return $this->container->get('bns.right_manager')->getCurrentGroupId();
    }

    public function currentGroup()
    {
        return $this->container->get('bns.right_manager')->getCurrentGroup();
    }

	/**
	 * @return string 
	 */
	public function getName()
    {
        return 'group_extension';
    }
}
