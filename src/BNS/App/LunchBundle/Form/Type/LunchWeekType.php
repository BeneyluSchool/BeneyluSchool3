<?php

namespace BNS\App\LunchBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LunchWeekType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', 'text', array(
            'required' => false,
            'attr' => array('placeholder' => 'EVENT_NAME'),
        ));
        $builder->add('description', 'textarea', array(
            'required' => false,
            'attr' => array('placeholder' => 'EVENT_DESCRIPTION'),
        ));
        $builder->add('sections', 'choice', array(
            'choices' => array(
                'full_menu' => 'FREE_ENTRY',
                'starter' => 'STARTER',
                'main_course' => 'MAIN_COURSE',
                'accompaniment' => 'ACCOMPANIMENT',
                'dairy' => 'DAIRY',
                'dessert' => 'DESSERT',
                'afternoon_snack' => 'AFTERNOON_SNACK'
            ),
            'multiple' => true,
            'expanded' => true,
        ));
        $builder->add('lunch_days', 'collection', array(
            'type' => new LunchDayType(),
        ));
    }

    public function getName()
    {
        return 'lunch_week';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\LunchBundle\Model\LunchWeek',
            'translation_domain' => 'LUNCH'
        ));
    }
}
