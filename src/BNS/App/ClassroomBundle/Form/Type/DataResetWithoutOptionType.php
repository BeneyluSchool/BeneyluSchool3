<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class DataResetWithoutOptionType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		$builder->add('id', 'hidden', array(
			'required'	     => true,
            'error_bubbling' => true
		));
	}
	
	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\CoreBundle\Model\Group',
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'no_option_data_reset_form';
	}
}