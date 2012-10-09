<?php

namespace BNS\App\MessagingBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use \SoapClient;
use BNS\App\CoreBundle\Model\UserQuery;

/**
 * Utilitaire de requête API Messagerie GoLive!
 *
 * @author Brian Clozel
 */
class MessagingAPICommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
                ->setName('bns:messaging-api')
                ->setDescription('API de Messagerie GoLive!');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output 
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
//        $this->writeSection($output, 'Delete all mailbox: ');
//
//        $users = UserQuery::create()->find();
//        
//        foreach ($users as $user) {
//            $username = $user->getSlug();
//            //Création de l'email sous la forme username@domain
//            $email = $username."@".$this->getContainer()->getParameter('mail_domain');
//            
//            //Appel api SOAP
//            $soapClient = new SoapClient($this->getContainer()->getParameter('mail_api_provi'));
//            $request = array(
//                'password' => $this->getContainer()->getParameter('mail_password'),
//                'mailAddress' => $email,
//                'domainId' => $this->getContainer()->getParameter('mail_domain_id'),
//                'transactionId' => time()."".$user->getId(),
//                'messageId' => 1,
//                'quota' => $this->getContainer()->getParameter('mail_quota'),
//                'status' => 1);
//            
//            $response = $soapClient->deleteAccount($request);
//            //$this->writeSection($output, json_encode($response));
//            //Si le code retour ne correspond pas à Mailbox_created : on rempile
//            if($response->code != 5)
//            {
//                $this->writeSection($output, 'ERROR for '.$username);
//            }
//            else
//            {
//                $this->writeSection($output, 'Mailbox deleted for '.$username);
//            }
//        }

        //---------------
        
        $this->writeSection($output, 'Create all mailbox: ');

        $users = UserQuery::create()->find();
        
        foreach ($users as $user) {
            $username = $user->getSlug();
            //Création de l'email sous la forme username@domain
            $email = $username."@".$this->getContainer()->getParameter('mail_domain');
            
            //Appel api SOAP
            $soapClient = new SoapClient($this->getContainer()->getParameter('mail_api_provi'));
            $request = array(
                'password' => $this->getContainer()->getParameter('mail_password'),
                'mailAddress' => $email,
                'domainId' => $this->getContainer()->getParameter('mail_domain_id'),
                'transactionId' => time()."".$user->getId(),
                'messageId' => 1,
                'quota' => $this->getContainer()->getParameter('mail_quota'),
                'status' => 1);
            
            $response = $soapClient->createMailBox($request);
            //$this->writeSection($output, json_encode($response));
            //Si le code retour ne correspond pas à Mailbox_created : on rempile
            if($response->code != 3)
            {
                $this->writeSection($output, 'ERROR for '.$username);
                $this->writeSection($output, json_encode($response));
            }
            else
            {
                $this->writeSection($output, 'Mailbox created for '.$username);
            }
        }

        
    }

    protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(
                $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true)
        );
    }

}
