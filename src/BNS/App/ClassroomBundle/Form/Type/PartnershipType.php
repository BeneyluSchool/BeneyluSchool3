<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Count;

/**
 * @author El Mehdi Ouarour <el-mehdi.ouarour@atos.net>
 */
class PartnershipType extends AbstractType
{
    const FORM_NAME = 'partnership_form';
		
    /**
     * @param FormBuilderInterface $builder
     * @param array $options 
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {	
        $builder->add('name', 'text', array(
                'required'	=> true,
        ));

        $builder->add('description', 'textarea', array(
                'required'	=> false,
        ));

        if (!count($options['classrooms'])) {
            $builder->add('home_message', 'textarea', array(
                'required'	=> false,
            ));
        }

        if (count($options['classrooms'])) {
            $builder->add('classrooms', 'model', [
                'class' => 'BNS\\App\\CoreBundle\\Model\\Group',
                'choices' => $options['classrooms'],
                'choices_as_values' => true,
                'choice_label' => 'label',
                'group_by' => 'getFullParentLabel',
                'expanded' => true,
                'multiple' => true,
                'constraints' => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'INVALID_PARTNER_CLASSROOM_EMPTY',
                    ]),
                ],
                'label' => 'LABEL_PARTICIPATING_CLASSROOMS',
                'attr' => [
                    'bns-group-by-icon' => '{ type: "SCHOOL" }',
                ],
                'choice_attr' => function () {
                    return [
                        'icon' => [ 'type' => 'CLASSROOM' ],
                        'class' => 'boxed-choice',
                    ];
                },
            ]);
        }

    }
	
    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\ClassroomBundle\Form\Model\PartnershipFormModel',
            'classrooms' => [],
        ));
    }
	
    /**
     * @return string 
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}
