<?php

namespace BNS\App\CampaignBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text');
        $builder->add('title', 'text');
        $builder->add('message','textarea');
        $builder->add('status', 'hidden');
        $builder->add('scheduled_at', 'datetime', array(
            'widget' => 'single_text',
            'date_format' => 'dd-MMMM-yyyy',
        ));
        $builder->add('resource-joined', 'hidden', [
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CampaignBundle\Model\Campaign'
        ));
    }

    public function getName()
    {
        return 'add_campaign';
    }
}
