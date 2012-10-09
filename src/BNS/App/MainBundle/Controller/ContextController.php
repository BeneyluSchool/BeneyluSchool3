<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

class ContextController extends Controller
{
	/**
     * Permet de changer de contexte
	 * 
     * @Route("/changer-de-contexte/{slug}", name="BNSAppMainBundle_switch_context")
     */
    public function switchContextAction($slug)
    {
    	$rightManager = $this->get('bns.right_manager');
    	$group = $this->get('bns.group_manager')->findGroupBySlug($slug);
        $rightManager->switchContext($group);

		// If AJAX, do NOT redirect the user
		if ($this->getRequest()->isXmlHttpRequest()) {
			return new Response();
		}
		
        return $this->redirect($this->generateUrl($rightManager->getRedirectRouteOfCurrentGroup()));
    }
}