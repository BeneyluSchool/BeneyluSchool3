<?php

namespace BNS\App\GroupBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EditGroupType extends AbstractType
{
	const FORM_NAME = 'edit_group_form';
		
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		$builder->add('home_message', 'textarea', array(
			'label'	=> "Message d'accueil :"
		));
	}
	
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\GroupBundle\Form\Model\EditGroupFormModel',
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