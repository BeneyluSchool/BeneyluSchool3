<?php

namespace BNS\App\ResourceBundle\Form\Type;

use BNS\App\ResourceBundle\DataReset\ChangeYearResourceDataReset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearResourceDataResetType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		$builder->add('choice', 'choice', array(
			'required'	     => true,
			'choices'	     => ChangeYearResourceDataReset::getChoices(),
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
			'data_class' => 'BNS\App\ResourceBundle\DataReset\ChangeYearResourceDataReset',
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'resource_data_reset_form';
	}
}