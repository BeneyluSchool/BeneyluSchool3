<?php

namespace BNS\App\MailerBundle\Mailer;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 */
class BNSMailer extends ContainerAware
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
     * Renvoie l'email de l'équipe Beneylu School
     */
    public function getAdminEmail()
    {
        $container = BNSAccess::getContainer();
        return $container->getParameter('beneyluschool_email');
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

	public function send($uniqueName, array $variables, $email, $lang = 'fr', $layouts = array(), $baseUrl = null)
	{
        $container = BNSAccess::getContainer();
        if($baseUrl == null && $container)
        {
            if($container->isScopeActive('request'))
            {
                $baseUrl = BNSAccess::getCurrentUrl();
            }else{
                $baseUrl = $container->getParameter(('application_base_url'));
            }
        }
		$msg = array(
			'email_template_unique_name'	=> $uniqueName,
			'variables'						=> $variables,
			'lang'							=> $lang,
			'email'							=> $email,
			'layouts'						=> $layouts,
            'base_url'                      => $baseUrl
		);
		// On envoie au RMQ 
		$this->mailerProducer->publish(serialize($msg));
	}

    public function sendSimple($users, $title, $content)
    {
        $emailed = array();
        foreach($users as $user)
        {
            $emailed[] = $user;
        }
        $this->sendMultiple('EMPTY',array('title' => $title, 'content' => $content), $emailed);
    }
	
	/**
	 * Send e-mail to a unique User object
	 * 
	 * @param string							$uniqueName
	 * @param array								$variables
	 * @param \BNS\App\CoreBundle\Model\User	$user
	 * @param array<String, String>				$layouts
	 */

	public function sendUser($uniqueName, array $variables, User $user, $layouts = array(), $baseUrl = null)
	{
	    if (null != $user->getNotificationEmail()) {
	        $this->send($uniqueName, $variables, $user->getNotificationEmail(), $user->getLang(), $layouts, $baseUrl);
		}elseif (null != $user->getEmail()) {
			$this->send($uniqueName, $variables, $user->getEmail(), $user->getLang(), $layouts, $baseUrl);
        }
	}
	
	/**
	 * Send e-mail to multiple User objects or user string assoc array
	 * 
	 * @param string					$uniqueName
	 * @param array						$variables
	 * @param array						$users
	 * @param array<String, String>		$layouts
	 */
	public function sendMultiple($uniqueName, array $variables, array $users, $layouts = array(), $baseUrl = null)
	{
        $container = BNSAccess::getContainer();
        if($baseUrl == null)
        {
            if($container->isScopeActive('request'))
            {
                $baseUrl = BNSAccess::getCurrentUrl();
            }else{
                $baseUrl = $container->getParameter(('application_base_url'));
            }
        }
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
			'layouts'						=> $layouts,
            'base_url'                      => $baseUrl
		);
				
		// On envoie au RMQ 
		$this->mailerProducer->publish(serialize($msg));
	}
}