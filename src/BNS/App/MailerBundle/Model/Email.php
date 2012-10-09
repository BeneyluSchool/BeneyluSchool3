<?php

namespace BNS\App\MailerBundle\Model;

use BNS\App\CoreBundle\Model\EmailTemplatePeer;
use BNS\App\CoreBundle\Model\EmailTemplateQuery;

/**
 * @author Eric Chau		<eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet	<sylvain.lorinet@pixel-cookers.com>
 */
class Email extends \Swift_Message
{
	/**
	 * @var string
	 */
	private $emailTemplateUniqueName;
	
	/**
	 * @var string
	 */
	private $lang;
	
	/**
	 * Constructeur de la classe Email; si vous souhaitez que votre email suit un template d'email, veuillez renseigner
	 * le paramètre $emailTemplateUniqueName qui correspond au unique name du template d'email que vous souhaitez employer;
	 * dans le cas contraire, fournissez aucun paramètre
	 * 
	 * @param string		$email
	 * @param string		$templateUniqueName
	 * @param type			$logger
	 * @param array<String>	$variables
	 * @param string		$lang
	 * 
	 * @throws Exception
	 */
	public function __construct($email, $templateUniqueName, $templating, $logger, array $variables = array(), $lang, array $layouts, $applicationBaseUrl)
	{
		try {
			parent::__construct();
			
			$this->emailTemplateUniqueName = $templateUniqueName;
			$this->lang = $lang;
			
			$this->setTo($email);

			// On récupère l'objet EmailTemplate à partir de l'unique name
			$emailTemplate = EmailTemplateQuery::create()
				->joinWithI18n($this->lang)
				->add(EmailTemplatePeer::UNIQUE_NAME, $this->emailTemplateUniqueName)
			->findOne();

			if (!$emailTemplate) {
				throw new \InvalidArgumentException('The email template unique name : ' . $this->emailTemplateUniqueName . ' does NOT exist !');
			}

			$emailTemplateVars = preg_split("/,/", $emailTemplate->getVars());

			// On vérifie que toutes les variables requises par le template d'email sont renseignés
			foreach ($emailTemplateVars as $var) {
				if (!isset($variables[$var])) {
					// Si la valeur d'une variable n'est pas renseignée on lève une exception
					throw new \InvalidArgumentException('The variable "' . $var . '" is missing !');
				}
			}

			// On récupère les modèles pour le corps et le sujet de l'email
			$htmlBody	= $emailTemplate->getHtmlBody();
			$plainBody	= $emailTemplate->getPlainBody();
			$subject	= $emailTemplate->getSubject();
			
			// On remplace chaque variable par les valeurs fournies et contenues dans le tableau fourni en paramètre de la fonction
			foreach ($variables as $key => $value) {
				$htmlBody	= preg_replace('/%' . $key . '%/', $value, $htmlBody);
				$plainBody	= preg_replace('/%' . $key . '%/', $value, $plainBody);
				$subject	= preg_replace('/%' . $key . '%/', $value, $subject);
			}
			
			$htmlBody = $templating->render(isset($layouts['html']) ? $layouts['html'] : $emailTemplate->getHtmlLayout(), array(
				'baseUrl'	=> $applicationBaseUrl,
				'title'		=> $emailTemplate->getSubject(),
				'body'		=> $htmlBody
			));
			
			$plainBody = $templating->render(isset($layouts['plain']) ? $layouts['plain'] : $emailTemplate->getPlainLayout(), array(
				'baseUrl'	=> $applicationBaseUrl,
				'title'		=> $emailTemplate->getSubject(),
				'body'		=> $plainBody
			));

			// On set le sujet et le corps de l'email que l'on vient de générer à partir du template d'email
			$this->setBody($htmlBody, 'text/html');
			$this->addPart($plainBody, 'text/plain');
			$this->setSubject($subject);
		}
		catch (Exception $e) {
			$logger->err('ERROR E-mail [' . $templateUniqueName . '] - ' . $e->getMessage());
		}
	}
		
	/*
	 * Getter de l'attribut emailTemplateUniqueName
	 * 
	 * @return string 
	 */
	public function getEmailTemplateUniqueName()
	{
		return $this->emailTemplateUniqueName;
	}
}