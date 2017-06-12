<?php

namespace BNS\App\RegistrationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class SchoolCreationType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('country',		'country', array(
			'preferred_choices' => array('FR')
		));
        $builder->add('uai',			'text');
        $builder->add('name',			'text');
        $builder->add('address',		'text');
        $builder->add('zip_code',		'text');
        $builder->add('city',			'text');
        $builder->add('email',			'text');
        $builder->add('phone_number',	'text', array('required' => false));
        $builder->add('fax_number',		'text', array('required' => false));
        $builder->add('group_id','text', array('required' => false));
    }
	
	/**
	 * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\RegistrationBundle\Model\SchoolInformation',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'school_creation_form';
    }
}