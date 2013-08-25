<?php

namespace BNS\App\GPSBundle\Form\Type;

use BNS\App\GPSBundle\DataReset\ChangeYearGPSDataReset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearGPSDataResetType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		$builder->add('choice', 'choice', array(
			'required'	     => true,
			'choices'	     => ChangeYearGPSDataReset::getChoices(),
			'empty_value'    => 'Veuillez choisir',
            'error_bubbling' => true
		));
	}
	
	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\GPSBundle\DataReset\ChangeYearGPSDataReset',
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'gps_data_reset_form';
	}
}