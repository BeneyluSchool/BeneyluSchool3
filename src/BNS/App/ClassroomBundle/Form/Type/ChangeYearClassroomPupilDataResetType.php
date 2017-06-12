<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use BNS\App\ClassroomBundle\DataReset\ChangeYearClassroomPupilDataReset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearClassroomPupilDataResetType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder
            ->add('choice', 'choice', array(
                'required'	     => true,
                'choices'	     => ChangeYearClassroomPupilDataReset::getChoices(),
                'empty_value'    => 'PLEASE_CHOOSE',
                'error_bubbling' => true
            ))
            ->add('uid', 'text', array(
                'required'       => false,
                'error_bubbling' => true
            ))
        ;
	}
	
	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ClassroomBundle\DataReset\ChangeYearClassroomPupilDataReset',
        'translation_domain' => 'CLASSROOM'
            ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'classroom_pupil_data_reset_form';
	}
}