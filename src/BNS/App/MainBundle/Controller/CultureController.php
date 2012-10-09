<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class CultureController extends Controller
{
	/**
     * Change la culture de l'utilisateur en cours
	 * 
     * @param string $culture : Culture à setter
	 * 
     * @Route("/switch-language/{culture}", name="BNSAppMainBundle_change_culture")
     */
	public function changeCultureAction(Request $request, $culture)
    {
    	if (array_search($culture, $this->container->getParameter('available_languages')) === false) {
    		throw $this->createNotFoundException('La langue ' . $culture . ' n\'existe pas !');
    	}
    	    	
		$session = $this->get('session');
    	$session->set('_locale', $culture);

    	return $this->redirect($request->headers->get('referer'));
    }
}