<?php

namespace BNS\App\ResourceBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ResourceType extends AbstractType
{
	public function __construct($resource = null)
	{
		$this->resource = $resource;
	}
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Titre
		$builder->add('label', 'text');
		// Description
		$builder->add('description', 'textarea',array('required' => false));
		$builder->add('id', 'hidden');
		
		if($this->resource){
			if($this->resource->isValueable()){
				$builder->add('value', 'text');
			}
		}
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ResourceBundle\Model\Resource',
			'csrf_protection' => false
        ));
    }

	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'resource';
	}
	

}