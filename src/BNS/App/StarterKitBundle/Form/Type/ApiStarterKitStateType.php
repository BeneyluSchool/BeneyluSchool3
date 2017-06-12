<?php

namespace BNS\App\StarterKitBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ApiStarterKitStateType
 *
 * @package BNS\App\StarterKitBundle\Form\Type
 */
class ApiStarterKitStateType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('enabled');
        $builder->add('last_step', 'text', [
            'mapped' => false, // handled by controller + manager
        ]);
        $builder->add('done', 'checkbox', [
            'mapped' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\\App\\StarterKitBundle\\Model\\StarterKitState',
        ]);
    }

    public function getName()
    {
        return 'api_starter_kit_state';
    }

}
