<?php

namespace BNS\App\GroupBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ImportFromCSVType extends AbstractType
{
	const FORM_NAME = 'import_from_csv_form';
    private $type;
    private $typeName;
    
    public function __construct($type="pupil", $typeName="PUPILS") {
        $this->type = $type;
        $this->typeName = $typeName;
    }
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
				
		// Fichier CSV
		$builder->add('file', 'file', array(
			'label'		=> 'FILE_PATH',
			'required' 	=> true,
		));
        
        $builder->add('type', 'hidden', array(
            'data'   => $this->type,
			'required'      => true,
		));
        
        $builder->add('type_name', 'hidden', array(
            'data'   => $this->typeName,
			'required'      => true,
		));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'GROUP'
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