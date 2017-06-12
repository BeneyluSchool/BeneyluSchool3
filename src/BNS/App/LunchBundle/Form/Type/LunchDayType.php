<?php

namespace BNS\App\LunchBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use BNS\App\LunchBundle\Model\LunchDay;

class LunchDayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('status', 'choice', array(
            'choices' => array(
                LunchDay::STATUS_NORMAL => 'STATUS_NORMAL',
                LunchDay::STATUS_SPECIAL => 'STATUS_SPECIAL',
                LunchDay::STATUS_NO_LUNCH => 'STATUS_NO_LUNCH',
            ),
        ));
        $builder->add('full_menu', 'textarea', array('label' => 'MENU_DETAIL'));
        $builder->add('starter', 'textarea', array('label' => 'STARTER'));
        $builder->add('main_course', 'textarea', array('label' => 'MAIN_COURSE'));
        $builder->add('dessert', 'textarea', array('label' => 'DESSERT'));
        $builder->add('dairy', 'textarea', array('label' => 'DAIRY'));
        $builder->add('accompaniment', 'textarea', array('label' => 'ACCOMPANIMENT'));
        $builder->add('afternoon_snack', 'textarea', array('label' => 'AFTERNOON_SNACK'));
    }

    public function getName()
    {
        return 'lunch_day';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\LunchBundle\Model\LunchDay',
            'translation_domain' => 'LUNCH'
        ));
    }
}
