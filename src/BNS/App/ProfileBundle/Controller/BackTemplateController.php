<?php

namespace BNS\App\ProfileBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpKernel\Exception\HttpException;

use BNS\App\CoreBundle\Model\ProfileQuery;
use BNS\App\CoreBundle\Model\ProfilePeer;

/**
 * @author Eric
 */
class BackTemplateController extends Controller
{
    /**
	 * @Route("/", name="profile_back_template_index")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 */
    public function indexAction()
    {    		
		$userProfile = ProfileQuery::create()
			->add(ProfilePeer::ID, $this->getUser()->getProfileId())
		->findOne();
		
        return $this->render('BNSAppProfileBundle:BackTemplate:back_template_index.html.twig', array(
			'templatable_object'				=> $userProfile,
			'route_to_template_content_preview' => $this->generateUrl('profile_back_template_preview'),
		));
    }
	
	/**
	 * @Route("/apercu-theme", name="profile_back_template_preview", options={"expose"=true})
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 */
	public function themePreviewAction()
	{
		$request = $this->getRequest();
		if (!$request->isXmlHttpRequest() || 'POST' != $request->getMethod())
		{
			throw new HttpException('500', 'Request must be AJAX and POST\' method!');
		}
		
		if (null == $request->get('theme_css_class_for_preview'))
		{
			throw HttpException('500', '\'theme_to_preview\' parameter mush be provide!');
		}
		
		$user = $this->getUser();
		$user->setProfile(ProfileQuery::create()
			->add(ProfilePeer::ID, $user->getProfileId())
		->findOne());
			
		
		return $this->render('BNSAppProfileBundle:BackTemplate:theme_preview.html.twig', array(
			'theme_css_class_for_preview'	=> $request->get('theme_css_class_for_preview'),
			'user'				=> $user,
		));
	}
}