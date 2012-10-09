<?php

namespace BNS\App\MailerBundle\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use BNS\App\MailerBundle\Model\Email;

/**
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 */
class BNSMailerConsumer implements ConsumerInterface
{
	protected $mailer;
	protected $logger;
	protected $templating;
	protected $default_email;
	protected $default_name;
	protected $applicationBaseUrl;

	/**
	 * @param type $mailer
	 * @param type $logger
	 * @param type $templating
	 * @param type $default_email
	 * @param type $default_name
	 */
	public function __construct($mailer, $logger, $templating, $default_email, $default_name, $applicationBaseUrl)
	{
		$this->mailer				= $mailer;
		$this->logger				= $logger;
		$this->templating			= $templating;
		$this->default_email		= $default_email;
		$this->default_name			= $default_name;
		$this->applicationBaseUrl	= $applicationBaseUrl;
	}

	/**
	 * @param \PhpAmqpLib\Message\AMQPMessage $msg
	 */
	public function execute(AMQPMessage $msg)
	{	
		$params = unserialize($msg->body);
		
		// Multiple e-mails
		if (isset($params['emails'])) {
			foreach ($params['emails'] as $key => $emailToSend) {
				$this->send($emailToSend, $params['email_template_unique_name'], $params['variables'], $params['langs'][$key], $params['layouts']);
			}
		}
		// Unique e-mail
		else {
			$this->send($params['email'], $params['email_template_unique_name'], $params['variables'], $params['lang'], $params['layouts']);
		}
	}
	
	/**
	 * @param string				$emailToSend
	 * @param string				$emailTemplateUniqueName
	 * @param array					$variables
	 * @param string				$lang
	 * @param array<String, String> $layouts
	 */
	public function send($emailToSend, $emailTemplateUniqueName, $variables, $lang, $layouts)
	{
		try{
			$email = new Email($emailToSend, $emailTemplateUniqueName, $this->templating, $this->logger, $variables, $lang, $layouts, $this->applicationBaseUrl);
			// L'expéditeur est configuré par défaut
			$email->setFrom(array($this->default_email => $this->default_name));
			// Finally
			$this->mailer->send($email);
		}catch (Exception $e) {
			$this->logger->err('ERROR E-mail ' . $e->getMessage());
		}
	}
}
