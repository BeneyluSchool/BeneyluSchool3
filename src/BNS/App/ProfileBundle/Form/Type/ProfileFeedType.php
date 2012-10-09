<?php

namespace BNS\App\ProfileBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ProfileFeedType extends AbstractType
{
	const FORM_NAME = 'profile_feed_form';
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Texte du statut
		$builder->add('text', 'textarea', array(
			'required'	=> true
		));
		
		// Id de l'image associé au statut
		$builder->add('resourceId', 'hidden');
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\ProfileBundle\Form\Model\ProfileFeedFormModel',
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