<?php

namespace BNS\App\ClassroomBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EditTeamType extends AbstractType
{
	const FORM_NAME = 'edit_team_form';

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Nom
		$builder->add('name', 'text', array(
			'label'		=> 'LABEL_NAME',
			'required'	=> true,
		));

        $builder->add('expirationDate', 'date', array(
            'input' => 'datetime',
            'widget'	=> 'single_text',
            'required'	=> false
        ));

		$builder->add('description', 'textarea', array(
			'label'	=> 'LABEL_DESCRIPTION'
		));
		$builder->add('welcomeMessage', 'textarea', array(
			'label'	=> 'LABEL_WELCOME_MESSAGE'
		));
	}

	/**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\ClassroomBundle\Form\Model\EditTeamFormModel',
            'translation_domain' => 'CLASSROOM'
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
