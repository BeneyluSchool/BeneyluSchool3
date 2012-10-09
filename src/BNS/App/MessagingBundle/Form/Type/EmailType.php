<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use BNS\App\MessagingBundle\Model\MailAttachment;

/**
 *
 * @author pierre-luc.rouays@atos.net
 */
class EmailType extends AbstractType
{
    
    public $id;
    public $to;
    public $subject;
    public $message;
    public $mustSave;
    
    public function __construct($id = null, $to = null, $subject = null, $message = null)
    {
        $this->id = $id;
        $this->to = $to;
        $this->subject = $subject;
        $this->message = $message;
        $this->mustSave = false;
    }

//    public function getDefaultOptions()
//    {
//        return array();
//    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden');
        $builder->add('mustSave', 'hidden');
        
        $builder->add('to', 'text');
        $builder->add('subject', 'text');
        $builder->add('message', 'textarea');
    }

    public function getName()
    {
        return "email_form";
    }

}