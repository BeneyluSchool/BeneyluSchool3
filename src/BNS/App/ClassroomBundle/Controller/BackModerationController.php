<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @author Eymeric
 */
class BackModerationController extends Controller
{
	/**
	 * @Route("/", name="BNSAppClassroomBundle_back_moderation")
	 * @Template()
	 */
	public function indexAction()
	{
		return array();
	}
	
	
}