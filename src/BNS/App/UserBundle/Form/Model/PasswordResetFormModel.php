<?php

namespace BNS\App\UserBundle\Form\Model;

use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MailerBundle\Mailer\BNSMailer;
use Symfony\Component\Routing\Router;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class PasswordResetFormModel
{
	/*
	 * Used by form type
	 */
	
	/**
	 * @var string 
	 */
	public $email;
	
	/**
	 * NB: parameters are injected by the controller from the container
	 * 
	 * @param \BNS\App\CoreBundle\User\BNSUserManager $userManager
	 */
	public function save(BNSUserManager $userManager, BNSMailer $mailer, Router $router)
	{
		$confirmationToken = $userManager->requestConfirmationResetPassword();
		$user = $userManager->getUser();
		
		$mailer->sendUser('REQUEST_RESET_PASSWORD', array(
			'first_name'		=> $user->getFirstName(),
			'confirmation_url'	=> $router->generate('user_password_reset_process', array(
				'confirmationToken'	=> $confirmationToken
			), true)
		), $user);
	}
}