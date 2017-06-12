<?php

namespace BNS\App\MainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class HomeNewType extends AbstractType
{
	const FORM_NAME = 'home_new_form';
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
        $builder->add('title', 'text', array('required' => true));
        $builder->add('description', 'textarea', array('required' => true));
        $builder->add('image_id', 'hidden', array('required' => false));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\MainBundle\Model\HomeNew',
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