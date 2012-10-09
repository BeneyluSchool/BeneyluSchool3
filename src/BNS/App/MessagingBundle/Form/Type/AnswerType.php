<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class AnswerType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder->add('answer','textarea',array('required' => false));
		$builder->add('conversation_id','hidden',array('required' => true));
    }
	
    public function getName()
    {
        return 'messaging_answer';
    }
}