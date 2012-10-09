<?php
namespace BNS\App\CoreBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class PermissionExtension extends Twig_Extension
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
            'has_right' => new Twig_Function_Method($this, 'hasRight', array()),
			'has_right_somewhere' => new Twig_Function_Method($this, 'hasRightSomeWhere', array())
        );
    }

    /**
	 * @param string $permissionName
	 * @param int $groupId
	 * 
	 * @return boolean 
	 */
    public function hasRight($permissionName, $groupId = null)
    {
		return $this->container->get('bns.right_manager')->hasRight($permissionName, $groupId);
    }
	
	    /**
	 * @param string $permissionName
	 * @param int $groupId
	 * 
	 * @return boolean 
	 */
    public function hasRightSomeWhere($permissionName)
    {
		return $this->container->get('bns.right_manager')->hasRightSomeWhere($permissionName);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'permission_extension';
    }
}
