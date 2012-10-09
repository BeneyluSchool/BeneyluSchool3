<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplateQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;

class EditClassroomType extends AbstractType
{
	const FORM_NAME = 'edit_classroom_form';
		
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Nom
		$builder->add('name', 'text', array(
			'label'		=> 'Nom :',
			'required'	=> true,
		));
		
		// Avatar_id
		$builder->add('avatarId', 'hidden', array(
			'required' => true,
		));
        
		$levelGroupTypeDataTemplate = GroupTypeDataTemplateQuery::create()
			->add(GroupTypeDataTemplatePeer::UNIQUE_NAME,  'LEVEL')
		->findOne();
		
		$choices = array();
		foreach ($levelGroupTypeDataTemplate->getGroupTypeDataChoicesJoinWithI18n() as $choice) {
			$choices[$choice->getLabel()] = $choice->getLabel();
		}
		
		// Niveau
		$builder->add('level', 'choice', array(
			'label'		=> 'Niveau :',
			'choices' 	=> $choices,
			'required' 	=> true,
			'expanded'	=> true,
			'multiple'	=> true
		));
		
		$builder->add('description', 'textarea', array(
			'label'	=> 'Description :'
		));
		
		$builder->add('home_message', 'textarea', array(
			'label'	=> "Message d'accueil :"
		));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ClassroomBundle\Form\Model\EditClassroomFormModel',
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