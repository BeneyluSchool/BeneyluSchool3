<?php
namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Model\User;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

class UserExtension extends Twig_Extension
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
            'is_child' => new Twig_Function_Method($this, 'isChild'),
            'is_adult' => new Twig_Function_Method($this, 'isAdult'),
            'is_authenticated' => new Twig_Function_Method($this, 'isAuthenticated'),
            'has_role_in_group' => new Twig_Function_Method($this, 'hasRoleInGroup'),
            'on_public_version' => new Twig_Function_Method($this, 'onPublicVersion'),
            'current_project' => new Twig_Function_Method($this, 'getCurrentProject'),
            'has_assistance' => new Twig_Function_Method($this, 'canActivateAssistance'),
        );
    }

	/**
	 * @param \BNS\App\CoreBundle\Twig\Extension\User $user
	 *
	 * @return boolean
	 */
    public function isChild(User $user = null)
    {
		if (null != $user) {
			return $this->container->get('bns.user_manager')->setUser($user)->isChild();
		}

		return $this->container->get('bns.right_manager')->isChild();
    }

	/**
	 * @param \BNS\App\CoreBundle\Twig\Extension\User $user
	 *
	 * @return boolean
	 */
	public function isAdult(User $user = null)
    {
		if (null != $user) {
			return $this->container->get('bns.user_manager')->setUser($user)->isAdult();
		}

		return $this->container->get('bns.right_manager')->isAdult();
    }

    /**
     * @param \BNS\App\CoreBundle\Twig\Extension\User $user
     *
     * @return boolean
     */
    public function isAuthenticated()
    {
        return $this->container->get('bns.right_manager')->isAuthenticated();
    }

    /**
     * @param \BNS\App\CoreBundle\Twig\Extension\User $user
     *
     * @return boolean
     */
    public function hasRoleInGroup($user, $roleUniqueName, $groupId = null)
    {
        if($groupId == null)
        {
            $groupId = $this->container->get('bns.right_manager')->getCurrentGroupId();
        }
        return $this->container->get('bns.user_manager')->setUser($user)->hasRoleInGroup($groupId, $roleUniqueName);
    }

	/**
	 * @return string
	 */
	public function getName()
    {
        return 'user_extension';
    }

    public function onPublicVersion()
    {
        if($this->isAuthenticated())
        {
            return $this->container->get('bns.right_manager')->getCurrentGroupManager()->isOnPublicVersion();
        }
        return false;
    }

    public function getCurrentProject()
    {
        if($this->container->get('bns.right_manager')->isAuthenticated())
        {
            return $this->container->get('bns.group_manager')->getProjectInfo('name');
        }else{
            return false;
        }
    }

    public function canActivateAssistance(User $user = null)
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $user = $user ? : $this->container->get('security.token_storage')->getToken()->getUser();

        return $this->container->get('bns.right_manager')->canActivateAssistance($user);
    }

}
