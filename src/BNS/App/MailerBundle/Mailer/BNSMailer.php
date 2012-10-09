<?php

namespace BNS\App\MailerBundle\Mailer;

use BNS\App\CoreBundle\Model\User;

/**
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 */
class BNSMailer
{	
	protected $mailerProducer;
        
	/**
	 * @param type $mailerProducer 
	 */
	public function __construct($mailerProducer = null)
	{
		$this->mailerProducer = $mailerProducer;
	}

	/**
	 * Send e-mail
	 * 
	 * @param string					$uniqueName
	 * @param array						$variables
	 * @param string					$email
	 * @param string					$lang
	 * @param array<String, String>		$layouts
	 */
	public function send($uniqueName, array $variables, $email, $lang, $layouts = array())
	{
		$msg = array(
			'email_template_unique_name'	=> $uniqueName,
			'variables'						=> $variables,
			'lang'							=> $lang,
			'email'							=> $email,
			'layouts'						=> $layouts
		);
				
		// On envoie au RMQ 
		$this->mailerProducer->publish(serialize($msg));
	}
	
	/**
	 * Send e-mail to a unique User object
	 * 
	 * @param string							$uniqueName
	 * @param array								$variables
	 * @param \BNS\App\CoreBundle\Model\User	$user
	 * @param array<String, String>				$layouts
	 */
	public function sendUser($uniqueName, array $variables, User $user, $layouts = array())
	{
		$this->send($uniqueName, $variables, $user->getEmail(), $user->getLang(), $layouts);
	}
	
	/**
	 * Send e-mail to multiple User objects or user string assoc array
	 * 
	 * @param string					$uniqueName
	 * @param array						$variables
	 * @param array						$users
	 * @param array<String, String>		$layouts
	 */
	public function sendMultiple($uniqueName, array $variables, array $users, $layouts = array())
	{
		$emails	= array();
		$langs	= array();
		
		if ($users[0] instanceof User) {
			foreach ($users as $user) {
				$emails[] = $user->getEmail();
				$langs[]  = $user->getLang();
			}
		}
		else {
			foreach ($users as $user) {
				$emails[] = $user['email'];
				$langs[]  = $user['lang'];
			}
		}
		
		$msg = array(
			'email_template_unique_name'	=> $uniqueName,
			'variables'						=> $variables,
			'langs'							=> $langs,
			'emails'						=> $emails,
			'layouts'						=> $layouts
		);
				
		// On envoie au RMQ 
		$this->mailerProducer->publish(serialize($msg));
	}
}