<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class ImportTeacherVcardType extends AbstractType
{
    const FORM_NAME = 'form_import_teacher_vcard';


    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('vcard', 'file', array('required' => true));
    }


    /**
     * @return string
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}
