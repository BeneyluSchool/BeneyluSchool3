<?php

namespace BNS\App\MessagingBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConversationType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('status', 'choice', array(
            'choices' => $options['statuses'],
            'required' => false,
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('statuses', []);
    }

    public function getName()
    {
        return 'messaging_conversation';
    }
}
