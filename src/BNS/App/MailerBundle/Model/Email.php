<?php

namespace BNS\App\MailerBundle\Model;

use BNS\App\CoreBundle\Model\EmailTemplatePeer;
use BNS\App\CoreBundle\Model\EmailTemplateQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Eric Chau        <eric.chau@pixel-cookers.com>
 * @author Sylvain Lorinet    <sylvain.lorinet@pixel-cookers.com>
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
     * @var
     */
    private $translator;

    /**
     * Constructeur de la classe Email; si vous souhaitez que votre email suit un template d'email, veuillez renseigner
     * le paramètre $emailTemplateUniqueName qui correspond au unique name du template d'email que vous souhaitez employer;
     * dans le cas contraire, fournissez aucun paramètre
     *
     * @param string                $email
     * @param string                $templateUniqueName
     * @param EngineInterface       $templating
     * @param LoggerInterface       $logger
     * @param array<String>         $variables
     * @param string                $lang
     * @param array                 $layouts
     * @param string                $baseUrl
     * @param string                $cdnUrl
     * @param TranslatorInterface   $translator
     *
     * @throws \Exception
     */
    public function __construct($email, $templateUniqueName, $templating, $logger, array $variables = array(), $lang, array $layouts, $baseUrl, $cdnUrl, TranslatorInterface $translator)
    {
        try {
            parent::__construct();

            $this->emailTemplateUniqueName = $templateUniqueName;
            $this->lang = $lang;
            $this->translator = $translator;
            $this->setTo($email);

            $this->translator->setLocale($lang);

            $emailTemplate = EmailTemplateQuery::create()
                ->filterByUniqueName($this->emailTemplateUniqueName, \Criteria::EQUAL)
            ->findOne();

            if (!$emailTemplate) {
                $logger->error('ERROR E-mail template invalid [' . $templateUniqueName . '] ');

                return false;
            }

            // On récupère les modèles pour le corps et le sujet de l'email
            /** @Ignore */
            $htmlBody   = $this->translator->trans($templateUniqueName . '_HTML', array(), 'EMAIL');
            /** @Ignore */
            $plainBody  = $this->translator->trans($templateUniqueName . '_PLAIN', array(), 'EMAIL');
            /** @Ignore */
            $subject    = $this->translator->trans($templateUniqueName . '_SUBJECT', array(), 'EMAIL');

            if (!isset($variables['baseUrl'])) {
                $variables['baseUrl'] = $baseUrl;
            }
            if (!isset($variables['cdnUrl'])) {
                $variables['cdnUrl'] = $baseUrl;
            }

            // On remplace chaque variable par les valeurs fournies et contenues dans le tableau fourni en paramètre de la fonction
            foreach ($variables as $key => $value) {
                $htmlBody   = preg_replace('/%' . $key . '%/', $value, $htmlBody);
                $plainBody  = preg_replace('/%' . $key . '%/', $value, $plainBody);
                $subject    = preg_replace('/%' . $key . '%/', $value, $subject);
            }

            // date du jour
            $today = date("d/m/Y");

            $htmlContent = $templating->render(isset($layouts['html']) ? $layouts['html'] : $emailTemplate->getHtmlLayout(), array(
                'baseUrl'   => $baseUrl,
                'cdnUrl'    => $baseUrl,
                'title'     => $subject,
                'body'      => $htmlBody,
                'date'      => $today,
                'lang'      => $lang,
            ));

            $plainContent = $templating->render(isset($layouts['plain']) ? $layouts['plain'] : $emailTemplate->getPlainLayout(), array(
                'baseUrl'     => $baseUrl,
                'title'       => $subject,
                'body'        => $plainBody,
                'date'        => $today
            ));

            // On set le sujet et le corps de l'email que l'on vient de générer à partir du template d'email
            $this->setBody($htmlContent, 'text/html');
            $this->addPart($plainContent, 'text/plain');
            $this->setSubject($subject);
        } catch (\Exception $e) {
            $logger->error('ERROR E-mail [' . $templateUniqueName . '] - ' . $e->getMessage());
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
