<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use BNS\App\CoreBundle\Access\BNSAccess;

class LogonController extends Controller
{
	/**
	 * @Route("/", name="home") 
	 */
    public function indexAction()
	{
		$right_manager = $this->get('bns.right_manager');
		
		// On soumet un refresh lorsque la centrale sera de retour sur l'app
		if (!$right_manager->isAuthenticated()) {
			$this->getRequest()->getSession()->set('need_refresh', true);
			
			// On transfert la target_path native de Symfony vers une custom, pour éviter la redirection dans l'IFrame.
			if ($this->getRequest()->getSession()->has('_security.oauth_area.target_path')) {
				$this->getRequest()->getSession()->set('_bns.target_path', $this->getRequest()->getSession()->get('_security.oauth_area.target_path'));
				$this->getRequest()->getSession()->remove('_security.oauth_area.target_path');
			}
			
			return $this->render('BNSAppMainBundle:Logon:index.html.twig',array('redirectUrl' => $this->container->getParameter('auth_url') . '/redirect/' . $this->container->getParameter('security.oauth.client_id')));
			
		}
		// IS_AUTHENTICATED (loggé)
		elseif ($this->getRequest()->getSession()->get('need_refresh', false)) {
			$this->getRequest()->getSession()->remove('need_refresh');
			
			// Launch all checks
			$redirect = $this->get('bns.user_manager')->onLogon();
			
			return $this->render('BNSAppMainBundle:Logon:refresh.html.twig', array(
				'redirect' => $redirect
			));
		}
		// Nous redirigeons selon la page accessible
		return $this->redirect($this->generateUrl($right_manager->getRedirectLoginRoute()));
	}
	
	/**
	 * @Route("/enter", name="enter") 
	 */
	public function enterAction()
	{
		if (BNSAccess::isConnectedUser()) {
			return $this->redirect($this->generateUrl('home'));
		}
		
		$parameters = array(
			'response_type' => 'code',
			'client_id'     => $this->container->getParameter('security.oauth.client_id'),
			'redirect_uri'  => $this->generateUrl('_security_login_check', array(), true)
		);
		
		return $this->render('BNSAppMainBundle:Logon:enter.html.twig', array(
			'authorizeUrl' => $this->container->getParameter('security.oauth.authorization_url') . '?' . http_build_query($parameters)
		));
	}
	
	/**
	 * Page des mentions légales
	 * @Route("/mentions-legales", name="main_logon_legals")
	 * @Template()
	 */
	public function legalsAction()
	{
		return array();	
	}
}