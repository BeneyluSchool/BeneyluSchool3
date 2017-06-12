<?php

namespace BNS\App\MainBundle\Controller;


use BNS\App\CoreBundle\Model\UserQuery;
use Predis\Connection\ConnectionException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;

class MonitoringController extends Controller
{
    private $hasError = false;
    private $errorMessage = "";


	/**
     * @Route("/monitoring", name="_monitoring")
     */
    public function monitoreAction()
    {
        $logger = $this->get('logger');
        $hasError = false;
        $errorMessage = "";
        //Test connexion Redis
        try{
            $redis = $this->get('snc_redis.default');
            $redis->connect();
        }catch (ConnectionException $e)
        {
            $logger->critical('Predis ERROR, connection aborted.');
            $this->addMessage($e);
        }catch(Exception $e)
        {
            $logger->critical('Predis ERROR check logs for further informations.');
            $this->addMessage($e);
        }

        //Test connexion BDD
        try{
           $query = UserQuery::create()->findOneById(1);
        }catch(\Exception $e)
        {
            $logger->critical('DB ERROR check logs for further informations.');
            $this->addMessage($e);
        }

        //Test connexion RabbitMQ
        try{
            $rabbit = $this->get('old_sound_rabbit_mq.connection.default');
            $rabbit->channel();

        }catch(\AMQPRuntimeException $e)
        {
            $logger->critical('RabbitMQ connexion error .');
            $this->addMessage($e);
        }catch(\Exception $e)
        {
            $logger->critical('RabbitMQ ERROR check logs for further informations.');
            $this->addMessage($e);
        }

        //Test de connexion annuaire
        $oauthUrl = $this->get('kernel')->getContainer()->getParameter('oauth_host') . '/monitoring';
        $buzz = $this->get('buzz');

        try{
            $buzz->get($oauthUrl);
            if($buzz->getLastResponse()->getStatusCode() != 200)
            {
                throw new \Exception('Oauth access error');
            }
        }catch(\Exception $e)
        {
            $logger->critical('Oauth access error');
            $this->addMessage($e);
        }

        if($this->hasError)
        {
            try{
                $message = \Swift_Message::newInstance()
                    ->setSubject('ALERTE MONITORING')
                    ->setFrom($this->get('kernel')->getContainer()->getParameter('email_default_from_email'))
                    ->setTo('eymeric.taelman@beneyluschool.com')
                    ->setBody($this->errorMessage,'text/html')
                ;
                $this->get('mailer')->send($message);
            }catch (\Exception $e)
            {
                $logger->critical('Simple email send error');
                $this->addMessage($e);
            }

            return new Response($this->errorMessage,500);
        }
        return new Response('OK',200);
    }

    private function addMessage($e)
    {
        $this->hasError = true;
        if($this->errorMessage == "")
        {
            $this->errorMessage = "<h1>Error</h1>";
        }
        $this->errorMessage .= '<h4>' . $e->getMessage() . '</h4>';
    }
}