<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ImportPupilFromCSVType extends AbstractType
{
	const FORM_NAME = 'import_user_from_csv_form';
		
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Sexe
		$builder->add('format', 'choice', array(
			'label'			=> 'Format du fichier',
			'choices'		=> array(
				0 => 'Format Beneylu School',
				1 => 'Format Base Elèves'
			),
			'required'		=> true,
			'expanded'		=> true,
			'multiple'		=> false,
			'empty_value'	=> false,
		));
				
		// Fichier CSV
		$builder->add('file', 'file', array(
			'label'		=> 'Chemin vers le fichier',
			'required' 	=> true,
		));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {

    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return self::FORM_NAME;
	}
}