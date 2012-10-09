<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class NewUserInClassroomType extends AbstractType
{
	const FORM_NAME = 'new_user_classroom_form';
	private $isNewTeacher;
	
	public function __construct($isNewTeacher = false) {
		$this->isNewTeacher = $isNewTeacher;
	}
		
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Nom
		$builder->add('last_name', 'text', array(
			'label'		=> 'Nom :',
			'required'	=> true,
		));
		
		// Prénom
		$builder->add('first_name', 'text', array(
			'label'		=> 'Prénom :',
			'required' => true,
		));
        
		// Sexe
		$builder->add('gender', 'choice', array(
                        'multiple' => false,
                        'expanded' => true,
			'label'		=> 'Sexe :',
			'choices' 	=> array(
				'M' => 'Masculin',
				'F' => 'Féminin'
			),
			'required' 	=> true,
                        'data' => 'M'
		));
				
		// Date d'anniversaire
		$builder->add('birthday', 'birthday', array(
			'years'		=> range(date('Y') - ($this->isNewTeacher? 80 : 18), date('Y')),
			'label'		=> 'Date de naissance :',
			'format'	=> 'dd/MM/yyyy',
			'required' 	=> false,
		));
		
		// Email
		if (true === $this->isNewTeacher)
		{
			$builder->add('email', 'email', array(
				'label'		=> 'Adresse électronique :',
				'required' => true,
			));
		}
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ClassroomBundle\Form\Model\NewUserInClassroomFormModel',
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