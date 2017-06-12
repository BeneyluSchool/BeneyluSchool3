<?php

namespace BNS\App\MediaLibraryBundle\Twig;

use BNS\App\CoreBundle\Access\BNSAccess;
use \BNS\App\CoreBundle\Model\Group;
use \BNS\App\CoreBundle\Model\User;

use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MediaLibraryBundle\Parser\PublicMediaParser;
use Symfony\Component\Config\Definition\Exception\Exception;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use \Symfony\Component\HttpKernel\Exception\HttpException;
use \Twig_Extension;
use \Twig_Function_Method;

/**
 * Twig Extension for resource support.
 *
 * @author  Eymeric Taelman <eymeric.taelman@pixel-cookers.com>
 */
class MediaExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var PublicMediaParser
     */
    protected $publicMediaParser;

    /*
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->publicMediaParser = $this->container->get('bns.media_library.public_media_parser');
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
			'getResourceDataUrl'				=> new Twig_Function_Method($this, 'getResourceDataUrl', array('is_safe' => array('html'))),
			'getResourceImageUrl'				=> new Twig_Function_Method($this, 'getResourceImageUrl', array('is_safe' => array('html'))),
            'getResourceImageUrlFromId'				=> new Twig_Function_Method($this, 'getResourceImageUrlFromId', array('is_safe' => array('html'))),
			'getFileTypeImageUrl'				=> new Twig_Function_Method($this, 'getFileTypeImageUrl', array('is_safe' => array('html'))),
			'getResourceUrl'					=> new Twig_Function_Method($this, 'getResourceUrl', array('is_safe' => array('html'))),
			'create_visualisation_url_resource' => new Twig_Function_Method($this, 'createVisualisationUrlResource', array('is_safe' => array('html'))),
			'parse_public_resources'			=> new Twig_Function_Method($this, 'parsePublicResources', array('is_safe' => array('html'))),
			'getResourceTemplateName'			=> new Twig_Function_Method($this, 'getResourceTemplateName', array('is_safe' => array('html'))),
			'avatar'							=> new Twig_Function_Method($this, 'getAvatar', array('is_safe' => array('html'))),
			'getResourceNavigationType'			=> new Twig_Function_Method($this, 'getResourceNavigationType', array('is_safe' => array('html'))),
			'group_type_icon'					=> new Twig_Function_Method($this, 'getGroupTypeIcon', array('is_safe' => array('html'))),
			'ajaxTiny'							=> new Twig_Function_Method($this, 'ajaxTiny', array('is_safe' => array('html'))),
            'canReadResource'					=> new Twig_Function_Method($this, 'canReadResource', array('is_safe' => array('html'))),
            'getImageUrlFromText'    			=> new Twig_Function_Method($this, 'getImageUrlFromText', array('is_safe' => array('html')))
        );
    }


	/*
	 * Affiche une image pour les resources
	 */
	public function getResourceDataUrl($media,$size = null)
    {
		return $this->createVisualisationUrlResource($media, true, false, $size);
    }

	/*
	 * Affiche une image pour les resources
	 */
	public function getResourceUrl($resource,$size = null)
    {
		$router = $this->getContainer()->get('router');
        if($resource)
        {
		    return $router->generate('BNSAppMediaLibraryBundle_download', array('resourceSlug' => $resource->getSlug(),'size' => $size),true);
        }else{
            return false;
        }
    }

    /**
     * @param Media $media
     * @param boolean $isPublic deprecated
     * @param boolean $isCli deprecated
     * @param string $size
     *
     * @return string
     */
    public function createVisualisationUrlResource(Media $media = null, $isPublic = false, $isCli = false, $size = "original")
    {
        return $this->publicMediaParser->createVisualisationUrlResource($media, $size);
    }

	public function parsePublicResources($text,$needConnexion = false, $size = 'medium', $light = false)
	{
		return $this->publicMediaParser->parse($text, $needConnexion, $size, $light);
	}

	/**
	 * @param type $resource
	 * @param type $size
	 *
	 * @return string
	 */
	public function getResourceImageUrl($resource, $size = 'thumbnail', $fallback = null)
    {
		if (null == $resource) {
            if($fallback == "miniSiteBanner")
            {
                return  $this->container->get('templating.helper.assets')->getUrl('/medias/images/mini-site/front/banner.jpg');
            }else{
			    return  $this->container->get('templating.helper.assets')->getUrl('/medias/images/profile/avatar/small/classroom.png');
            }
		}

		$rm = $this->getContainer()->get('bns.media.manager');
		$rm->setMediaObject($resource);
		if($rm->isThumbnailable()){
			return $this->createVisualisationUrlResource($resource, true, false, $size);
		}
		else{
			return $this->getFileTypeImageUrl($resource,$size);
		}
    }

    public function getResourceImageUrlFromId($resourceId, $size = 'thumbnail', $fallback = null)
    {
        if($resourceId != null)
        {
            $media = MediaQuery::create()->findOneById($resourceId);
            if($media)
            {
                return $this->getResourceImageUrl($media, $size, $fallback);
            }else{
                return false;
            }
        }
        return false;
    }

	public function getFileTypeImageUrl($media, $size = 'thumbnail')
	{
		$rm = $this->getContainer()->get('bns.media.manager');
		$rm->setMediaObject($media);
		return $rm->getFileTypeImageUrl($size);
	}

	public function getResourceTemplateName($resource)
    {
		$rm = $this->getContainer()->get('bns.media.manager');
		$rm->setMediaObject($resource);
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

				$avatarSrc = $this->container->get('templating.helper.assets')->getUrl('/medias/images/profile/avatar/' . $size . '/' . $mainRole . '.png');
			}
			else {
				$resource = $user->getProfile()->getResource();
				$rm = $this->getContainer()->get('bns.media.manager');
				$rm->setMediaObject($resource);
				$avatarSrc = $this->createVisualisationUrlResource($resource, true, false, $size);
			}
		}
		elseif ($object instanceof Group) {
			$group = $object;
			$avatarId = $group->getAttribute('AVATAR_ID');
			if (0 != $avatarId && $defaultAvatar == false) {
				$resource = MediaQuery::create()
					->add(MediaPeer::ID, $avatarId)
				->findOne();

				if (null === $resource) {
                    $group->setAttribute('AVATAR_ID',null);
                    $avatarSrc = $this->container->get('templating.helper.assets')->getUrl('/medias/images/icons/group_context/' . strtolower($group->getGroupType()->getType()) . '/' . $size . '.png');
				}else{
                    $rm = $this->getContainer()->get('bns.media.manager');
                    $avatarSrc = $this->createVisualisationUrlResource($resource, true, false, $size);
                }
			}
			else {
				$avatarSrc = $this->container->get('templating.helper.assets')->getUrl('/medias/images/icons/group_context/' . strtolower($group->getGroupType()->getType()) . '/' . $size . '.png');
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
	public function getGroupTypeIcon($group = null, $groupType = null, $size = "medium")
	{
		if(($group == null || $group == "") && $groupType != null){
			$trueGroupType = $groupType;
		}elseif($group != null && $group instanceof Group) {
            $trueGroupType = $group->getGroupType()->getType();
        } else {
            $trueGroupType = "CLASSROOM";
        }

		return $this->container->get('templating.helper.assets')->getUrl('/medias/images/icons/group_context/' . strtolower($trueGroupType  ) . '/' . $size . '.png');
	}

    protected function getAssetsUrl($inputUrl)
    {
        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->container->get('templating.helper.assets');

        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $assets->getUrl($this->baseUrl . $url);
        }

        return $inputUrl;
    }

	public function ajaxTiny($id,$content,$lang="fr")
    {

        $config = $this->container->getParameter('stfalcon_tinymce.config');

        // Get local button's image
        foreach ($config['tinymce_buttons'] as &$customButton) {
            if (isset($customButton['image']) && $customButton['image']) {
                $customButton['image'] = $this->getAssetsUrl($customButton['image']);
            } else {
                unset($customButton['image']);
            }

            if ($customButton['icon']) {
                $customButton['icon'] = $this->getAssetsUrl($customButton['icon']);
            } else {
                unset($customButton['icon']);
            }
        }

        /* @var Session $session */
        $session = BNSAccess::getSession();
        if($session->has('tiny_mce_plugins'))
        {
            $plugins = $session->get('tiny_mce_plugins');
            foreach($plugins as $plugin)
            {
                $config['theme']['simple']['toolbar1'] .= ' ' . $plugin;
            }
        }

        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->container->get('templating.helper.assets');

        // Get path to tinymce script for the jQuery version of the editor
        $config['jquery_script_url'] = $assets->getUrl('bundles/stfalcontinymce/vendor/tiny_mce/tiny_mce.jquery.js');

        // Get local button's image
        if (isset($config['tinymce_buttons'])) {
            foreach ($config['tinymce_buttons'] as &$customButton) {
                if (isset($customButton['image'])) {
                    $imageUrl = $customButton['image'];
                    $url      = preg_replace('/^asset\[(.+)\]$/i', '$1', $imageUrl);
                    if ($imageUrl !== $url) {
                        $customButton['image'] = $assets->getUrl($url);
                    }
                }
            }
        }

        if(!isset($_COOKIE['tinymce_mode'])) {
            $isChild = $this->container->get('bns.right_manager')->getUserManager()->isChild();
            $value = $isChild ? 'simple' : 'advanced';
            setCookie('tinymce_mode', $value);
        }


            $langs = array("fr" => 'fr_FR', "en" => 'en_GB');
            $config['language'] = $langs[$lang];

        return $this->container->get('templating')->render('BNSAppMediaLibraryBundle:Tiny:init.html.twig', array(
            'tinymce_config' => json_encode($config),'id' => $id,'content' => $content
        ));

    }

    public function canReadResource($resource, $forVisualisation = true)
    {
        $rrm = $this->getContainer()->get('bns.media_library_right.manager');
        return $rrm->canReadMedia($resource, $forVisualisation);
    }

    public function getImageUrlFromText($text) {
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $pattern = "/<img[^>]+\>/i";
        preg_match($pattern, $text, $matches);
        $text = $matches[0];
        $pattern = '/src=[\'"]?([^\'" >]+)[\'" >]/';
        preg_match($pattern, $text, $link);
        $link = $link[1];
        $link = urldecode($link);
        return $link;
    }

}
