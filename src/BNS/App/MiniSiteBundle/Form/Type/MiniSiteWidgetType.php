<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSiteWidgetType extends AbstractType
{
	/**
	 * @var array<String> Extra properties
	 */
	private $properties;
	
	/**
	 * @var string 
	 */
	private $namespace;
	
	/**
	 * @var int 
	 */
	private $id;
			
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text', array('required' => true));
		
		foreach ($this->properties as $property => $data) {
			if (is_array($data)) {
				$builder->add(strtolower($property), $data['input'], $data['options']);
			}
			else {
				$builder->add(strtolower($property), $data);
			}
		}
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->namespace,
        ));
    }


	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'mini_site_widget_form_' . $this->id;
    }
	
	/**
	 * @param array<String> $properties
	 */
	public function setProperties($properties)
	{
		$this->properties = $properties;
	}
	
	/**
	 * @param string $namespace
	 */
	public function setNamespace($namespace)
	{
		$this->namespace = $namespace;
	}
	
	/**
	 * @param int $id
	 */
	public function setId($id)
	{
		$this->id = $id;
	}
}