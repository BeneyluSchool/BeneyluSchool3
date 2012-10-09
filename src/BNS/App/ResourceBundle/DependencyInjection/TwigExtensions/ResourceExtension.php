<?php

namespace BNS\App\ResourceBundle\DependencyInjection\TwigExtensions;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\ResourceBundle\Model\ResourcePeer;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Twig_Extension;
use Twig_Function_Method;

/**
 * Twig Extension for resource support.
 *
 * @author  Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 */
class ResourceExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /*
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
			'getResourceDataUrl'			=> new Twig_Function_Method($this, 'getResourceDataUrl', array('is_safe' => array('html'))),
			'getResourceImageUrl'			=> new Twig_Function_Method($this, 'getResourceImageUrl', array('is_safe' => array('html'))),
			'getFileTypeImageUrl'			=> new Twig_Function_Method($this, 'getFileTypeImageUrl', array('is_safe' => array('html'))),
			'getResourceUrl'				=> new Twig_Function_Method($this, 'getResourceUrl', array('is_safe' => array('html'))),
			'getResourceTemplateName'		=> new Twig_Function_Method($this, 'getResourceTemplateName', array('is_safe' => array('html'))),
			'avatar'						=> new Twig_Function_Method($this, 'getAvatar', array('is_safe' => array('html'))),
			'getResourceNavigationType'		=> new Twig_Function_Method($this, 'getResourceNavigationType', array('is_safe' => array('html'))),
			'group_type_icon'				=> new Twig_Function_Method($this, 'getGroupTypeIcon', array('is_safe' => array('html')))
        );
    }

	
	/*
	 * Affiche une image pour les resources 
	 */
	public function getResourceDataUrl($resource,$size = null)
    {	
		$rm = $this->getContainer()->get('bns.resource_manager');
		$rm->setObject($resource);
		return $rm->getDataUrl($size);
    }
	
	/*
	 * Affiche une image pour les resources 
	 */
	public function getResourceUrl($resource,$size = null)
    {	
		$router = $this->getContainer()->get('router');
		return $router->generate('BNSAppResourceBundle_download', array('resource_slug' => $resource->getSlug(),'size' => $size),true);
    }
	
	/**
	 * @param type $resource
	 * @param type $size
	 * 
	 * @return string
	 */
	public function getResourceImageUrl($resource, $size = 'thumbnail')
    {
		if (null == $resource) {
			return '/medias/images/profile/avatar/small/classroom.png';
		}
		
		$rm = $this->getContainer()->get('bns.resource_manager');
		if($rm->isThumbnailable($resource)){
			return $this->getResourceDataUrl($resource, $size);
		}
		else{
			return $this->getFileTypeImageUrl($resource,$size);
		}
    }
	
	public function getFileTypeImageUrl($resource, $size = 'thumbnail')
	{
		$rm = $this->getContainer()->get('bns.resource_manager');
		$rm->setObject($resource);
		return $rm->getFileTypeImageUrl($size);
	}
	
	public function getResourceTemplateName($resource)
    {	
		$rm = $this->getContainer()->get('bns.resource_manager');
		$rm->setObject($resource);
		return $rm->getTemplateName();
    }
	
	public function getResourceNavigationType()
    {	
		return $this->getContainer()->get('bns.right_manager')->getSession()->get('resource_action_type');
    }
	
    public function getName()
    {
        return 'resource';
    }
	
	/**
	 * A appeler pour afficher les avatars
	 * 
	 * @return string renvoi le src vers l'avatar
	 */
	public function getAvatar($object, $size = 'small', $defaultAvatar = false)
	{
		$avatarSrc = '';
		if ($object instanceof User) {
			$user = $object;
			if (!$user->hasAvatar() || $defaultAvatar) {
				$mainRole = '';
				if (0 != $user->getHighRoleId()) {
					$mainRole = strtolower($this->container->get('bns.role_manager')->getGroupTypeRoleFromId($user->getHighRoleId())->getType());
				}

				switch ($mainRole) {
					case 'pupil':
						$mainRole .= '_' . $user->getGender();
						break;
					case 'parent':
					case 'teacher':
						break;
					default:
						$mainRole = 'classroom';
				}

				$avatarSrc = '/medias/images/profile/avatar/' . $size . '/' . $mainRole . '.png';
			}
			else {
				$resource = $user->getProfile()->getResource();
				$rm = $this->getContainer()->get('bns.resource_manager');
				$rm->setObject($resource);
				$avatarSrc = $rm->getDataUrl($size);
			}
		}
		elseif ($object instanceof Group) {
			$group = $object;
			$avatarId = $group->getAttribute('AVATAR_ID');
			if (0 != $avatarId) {
				$resource = ResourceQuery::create()
					->add(ResourcePeer::ID, $avatarId)
				->findOne();
				
				if (null === $resource) {
					throw new HttpException(500, 'Invalid avatar id!');
				}
				
				$rm = $this->getContainer()->get('bns.resource_manager');
				$rm->setObject($resource);
				$avatarSrc = $rm->getDataUrl($size);
			}
			else {
				$avatarSrc = '/medias/images/icons/group_context/' . strtolower($group->getGroupType()->getType()) . '-' . $size . '.png';
			}
		}
		
		return $avatarSrc;
	}
	/**
	 * Renvoie le chemin pour l'image d'un type de groupe
	 * @param Group $group : le groupe en question
	 * @param String $groupType : le type de groupe (string)
	 * @param String $size (null = small, sinon large)
	 * @return String
	 */
	public function getGroupTypeIcon($group = null, $groupType = null, $size = null)
	{
		if(($group == null || $group == "") && $groupType != null){
			$trueGroupType = $groupType;
		}else{
			$trueGroupType = $group->getGroupType()->getType();
		}
		
		if($size != 'large'){
			$size = "";
		}else{
			$size = "-large";
		}
		return '/medias/images/icons/group_context/' . strtolower($trueGroupType  ) . $size . '.png';
	}
	
}
