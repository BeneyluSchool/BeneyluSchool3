<?php

namespace BNS\App\SchoolBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class SchoolPupilType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('last_name', 'text', array(
			'required'		=> true,
			'max_length'	=> 45,
			'label'			=> 'Nom',
		));
		
		$builder->add('first_name', 'text', array(
			'required'		=> true,
			'max_length'	=> 45,
			'label'			=> 'Prénom',
		));
		
		$builder->add('email', 'email', array(
		'required'		=> true,
		'max_length'	=> 255,
		'label'			=> 'E-mail',
		));
	}
	
	public function getName()
	{
		return 'school_pupil_form';
	}
}