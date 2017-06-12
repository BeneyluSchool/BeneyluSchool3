<?php

namespace BNS\App\InfoBundle\Info;


use BNS\App\InfoBundle\Model\Contact;
use BNS\App\MailerBundle\Mailer\BNSMailer;

class InfoManager
{
    protected  $simplePieService;
    protected  $mailer;

    public function __construct(\SimplePie $simplePieService, BNSMailer $mailer)
    {
        $this->simplePieService = $simplePieService;
        $this->mailer = $mailer;
    }

    public function getItems($feedUrl,$start = 0,$end = 10)
    {
        $this->simplePieService->set_cache_class('BNS\App\InfoBundle\Info\MySimplePieCache');
        $this->simplePieService->set_feed_url($feedUrl);
        //SimplePie ne reclasse pas les éléments, on fait confiance au flux
        $this->simplePieService->enable_order_by_date(false);
        $this->simplePieService->init();
        return  $this->simplePieService->get_items($start, $end);
    }

    public function getItem($pos = 0)
    {
        return $this->simplePieService->get_item($pos);
    }

    public function newContactProcess(Contact $contact)
    {
        $contact->setDone(false);
        $contact->save();
        //Envoi du mail à l'utilisateur
        $this->mailer->send(
            'CONTACT_RECEIVED',
            array('contact_id' => $contact->getId() + 1000),
            $contact->getUser()->getEmail(),
            'fr'
        );

        $this->mailer->send(
            'CONTACT',
            array('content' => $contact->getDescription()),
            $this->mailer->getAdminEmail(),
            'fr'
        );
    }

}