<?php

namespace BNS\App\GpsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GpsPlaceType extends AbstractType
{
	const FORM_NAME = 'gps_place_form';
	
	public function __construct()
	{
		
	}
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Titre
		$builder->add('label', 'text',array('label'=> "Nom"));
		$builder->add('address', 'text',array('label' => "Adresse"));
		$builder->add('description', 'textarea',array('label' => 'Description'));
		$builder->add('id', 'hidden');
		$builder->add('gps_category_id', 'hidden');
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\GPSBundle\Model\GpsPlace',
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