<?php

namespace BNS\App\SchoolBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class SchoolTeacherType extends AbstractType
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
		
		$builder->add('role', 'choice', array(
			// FIXME Retrieve roles from CentralAuth
			'choices' 		=> array(0 => 'Directeur', 1 => 'Enseignant'),
			'required'		=> true,
			'empty_value' 	=> false,
			'multiple'		=> true,
			'expanded'		=> true,
			'label'			=> 'Rôle',
		));
	}
	
	public function getName()
	{
		return 'school_teacher_form';
	}
}