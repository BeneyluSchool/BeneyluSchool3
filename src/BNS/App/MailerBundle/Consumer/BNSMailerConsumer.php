<?php

namespace BNS\App\MailerBundle\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

use BNS\App\MailerBundle\Model\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;


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
	protected $cdnUrl;
	protected $last_sent;
    protected $translator;
    protected $beneylu;

	/**
	 * @param \Swift_Mailer $mailer
	 * @param LoggerInterface $logger
	 * @param EngineInterface $templating
	 * @param string $default_email
	 * @param string $default_name
     * @param string $cdnUrl
     * @param TranslatorInterface $translator
     * @param string $beneylu_brand_name
	 */
	public function __construct($mailer, $logger, $templating, $default_email, $default_name, $cdnUrl, $translator, $beneylu_brand_name, $default_reply_to)
	{
		$this->mailer				= $mailer;
		$this->logger				= $logger;
		$this->templating			= $templating;
		$this->default_email		= $default_email;
		$this->default_name			= $default_name;
        $this->default_reply_to     = $default_reply_to;
		$this->cdnUrl               = $cdnUrl;
        $this->translator           = $translator;
        $this->beneylu              = $beneylu_brand_name;
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
				$this->send($emailToSend, $params['email_template_unique_name'], $params['variables'], $params['langs'][$key], $params['layouts'], isset($params['base_url']) ? $params['base_url'] : null);
			}
		}
		// Unique e-mail
		else {
			if(isset($params['email'])){
				$this->send($params['email'], $params['email_template_unique_name'], $params['variables'], $params['lang'], $params['layouts'], isset($params['base_url']) ? $params['base_url'] : null);
			}
		}
	}

	/**
	 * @param string				$emailToSend
	 * @param string				$emailTemplateUniqueName
	 * @param array					$variables
	 * @param string				$lang
	 * @param array<String, String> $layouts
	 */
	public function send($emailToSend, $emailTemplateUniqueName, $variables, $lang, $layouts, $baseUrl)
	{
        $beneylu = array('beneylu_brand_name' => $this->beneylu);
        $variables = array_merge($variables, $beneylu);
		if (time() - $this->last_sent > 5) {
			$this->mailer->getTransport()->stop();
			$this->mailer->getTransport()->start();
			if (time() - $this->last_sent > 50) {
				\Propel::close();
			}
		}

        try {

			$email = new Email($emailToSend, $emailTemplateUniqueName, $this->templating, $this->logger, $variables, $lang, $layouts, $baseUrl, $this->cdnUrl, $this->translator);
			// L'expéditeur est configuré par défaut
			$email->setFrom(array($this->default_email => $this->default_name));
            $email->setReplyTo(array($this->default_reply_to => $this->default_name));

			// Finally
			$this->mailer->send($email);
			$this->logger->info('E-mail sent ' . $emailToSend . '"' . $email->getSubject() . '"');

			$this->last_sent = time();
        } catch (\Exception $e) {
            $this->logger->error('ERROR E-mail ' . $e->getMessage());
        }

	}
}
