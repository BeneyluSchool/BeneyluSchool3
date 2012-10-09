<?php

namespace BNS\App\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\FormError;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\UserBundle\Form\Type\PasswordGenerationType;
use BNS\App\UserBundle\Form\Type\PasswordResetType;

/**
 * @Route("/mot-de-passe")
 * 
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontPasswordController extends Controller
{
    /**
     * @Route("/", name="user_password")
     */
    public function indexAction()
    {
		$userManager = $this->get('bns.user_manager');
		$userManager->setUser($this->getUser());
		
		if (!$userManager->isRequestPassword()) {
			throw new NotFoundHttpException('You can NOT generate a new password without a request !');
		}
		
		$form = $this->createForm(new PasswordGenerationType());
		if ($this->getRequest()->isMethod('POST')) {
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()) {
				$form->getData()->save($this->get('bns.user_manager'));
				
				return $this->redirect($this->generateUrl('user_password_confirmation'));
			}
		}
		
        return $this->render('BNSAppUserBundle:Password:index.html.twig', array(
			'form'	=> $form->createView()
		));
    }
	
	/**
	 * @Route("/confirmation", name="user_password_confirmation")
	 */
	public function confirmAction()
	{
		$redirect = $this->get('bns.user_manager')->onLogon();
		if (false === $redirect) {
            // TODO translate
            $this->get('session')->getFlashBag()->add('success', 'Félicitations, le mot de passe a été modifié avec succès !');

			$redirect = $this->generateUrl('home');
		}
		
		// Force redirection
		return $this->redirect($redirect);
	}
	
	/**
	 * @Route("/reinitialisation", name="user_password_reset")
	 * @Anon
	 */
	public function resetAction()
	{
		$userManager = $this->get('bns.user_manager');
		$form = $this->createForm(new PasswordResetType());
		
		if ($this->getRequest()->isMethod('POST')) {
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()) {
				// From central
				$user = $userManager->getUserByEmail(urlencode($form->getData()->email));
				
				if (null == $user) {
					$form->get('email')->addError(new FormError("L'adresse e-mail renseignée est introuvable"));
				}
				else {
					if (null != $user->getPasswordRequestedAt() && ($user->getPasswordRequestedAt()->getTimestamp() + 1800) > time()) { // 30 min
						return $this->redirect($this->generateUrl('user_password_reset_warn'));
					}
					
					$userManager->setUser($user);
					$form->getData()->save($userManager, $this->get('bns.mailer'), $this->get('router'));
					
					return $this->redirect($this->generateUrl('user_password_reset_confirmation'));
				}
			}
		}
		
		return $this->render('BNSAppUserBundle:Password:reset.html.twig', array(
			'form'	=> $form->createView()
		));
	}
	
	/**
	 * @Route("/reinitialisation/confirmation", name="user_password_reset_confirmation")
	 * @Anon
	 */
	public function resetConfirmAction()
	{
		return $this->render('BNSAppUserBundle:Password:reset_confirmation.html.twig', array());
	}
	
	/**
	 * @Route("/reinitialisation/en-cours", name="user_password_reset_warn")
	 * @Anon
	 */
	public function resetWarnAction()
	{
		return $this->render('BNSAppUserBundle:Password:reset_warn.html.twig', array());
	}
	
	/**
	 * @Route("/reinitialisation/cle/{confirmationToken}", name="user_password_reset_process")
	 * @Anon
	 */
	public function resetProcessAction($confirmationToken)
	{
		$userManager = $this->get('bns.user_manager');
		$user = $userManager->getUserFromConfirmationToken($confirmationToken);
		
		if (null == $user) {
			throw new NotFoundHttpException('The confirmation token : ' . $confirmationToken . ' is NOT found !');
		}
		
		$userManager->resetUserPassword($user);
		$this->get('bns.api')->getRedisConnection()->del($this->get('bns.api')->getRoute(array(
			'confirmation_token' => $confirmationToken
		), 'user_confirmation_token'));
		
		return $this->render('BNSAppUserBundle:Password:reset_confirmation_success.html.twig');
	}
}