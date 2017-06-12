<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class SelectUsersCardType extends AbstractType
{
    const FORM_NAME = 'select_users_form';
    public $pupils;
    public $parents;


    public function __construct($c_pupils, $c_parents)
    {
        $this->pupils = $c_pupils;
        $this->parents = $c_parents;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $i=0;
        foreach ($this->pupils as $pupil) {
            $builder->add($i, 'checkbox', array(
                'label' => $pupil->getFullName(),
                'required' => false,
                'value' => $pupil->getId(),
                'data' => true
            ));
            $i++;
        }
        foreach ($this->parents as $pupil) {
            $builder->add($i, 'checkbox', array(
                'label' => $pupil->getFullName(),
                'required' => false,
                'value' => $pupil->getId(),
                'data' => true
            ));
            $i++;
        }
    }


    /**
     * @return string
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}
