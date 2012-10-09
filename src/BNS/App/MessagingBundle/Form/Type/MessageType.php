<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class MessageType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder->add('subject','text',array('required' => true));
		$builder->add('content','textarea',array('required' => false));
		$builder->add('to','hidden',array('required' => true));
		$builder->add('draftId','hidden',array('required' => false));
    }
	
    public function getName()
    {
        return 'messaging_message';
    }
}