<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class AnswerType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('answer', 'textarea');
        $builder->add('toAll', 'checkbox', array('required' => false));
        $builder->add('resource-joined', 'hidden', array(
            'required' => false,
        ));
    }

    public function getName()
    {
        return 'messaging_answer';
    }

}
