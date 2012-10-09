<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;

class FrontController extends Controller
{	
	/**
	 * @Route("/", name="BNSAppClassroomBundle_front")
	 * @Rights("CLASSROOM_ACCESS")
	 */
	public function indexAction()
	{
		return $this->render('BNSAppClassroomBundle:Front:front_classroom_index.html.twig', array(
			'message' => $this->get('bns.right_manager')->getCurrentGroupManager()->getAttribute('HOME_MESSAGE')
		));
	}
}