<?php

namespace BNS\App\HelloWorldBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * @Route("/admin/second") 
 */
class BackSecondController extends Controller
{
	/**
	 * @Route("/", name="BNSAppHelloWorldBundle_back_second")
	 * @Template()
	 */
	public function indexAction()
	{
		return array();
	}
}