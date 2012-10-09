<?php

namespace BNS\App\GroupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


class FrontController extends Controller
{
    
    /**
	 * @Route("/", name="BNSAppGroupBundle_front")
	 * @Template()
	 */
	public function indexAction()
    {
		$gm = $this->get('bns.right_manager')->getCurrentGroupManager();
		$group = $gm->getGroup();
		
		return array(
			"group_name"		=> $group->getLabel(),
			"group_home_message"	=> $group->getAttribute('HOME_MESSAGE')
		);	
    }
}
