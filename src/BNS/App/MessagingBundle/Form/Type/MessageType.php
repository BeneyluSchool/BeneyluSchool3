<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MessageType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('subject', 'text', array(
            'required' => !$options['draft'],
        ));
        $builder->add('content', 'textarea', array(
            'required' => !$options['draft'],
        ));
        $builder->add('to', 'hidden', array(
            'required' => !$options['draft'],
        ));
        $builder->add('draftId', 'hidden', array(
            'required' => false
        ));
        $builder->add('resource-joined', 'hidden', array(
            'required' => false
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('draft', false);
    }

    public function getName()
    {
        return 'messaging_message';
    }
}
