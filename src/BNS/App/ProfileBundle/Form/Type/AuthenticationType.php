<?php

namespace BNS\App\ProfileBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;

class AuthenticationType extends AbstractType
{
	const FORM_NAME = 'authentication_form';
	
	public function __construct()
	{
		
	}
        
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
        $builder->add('login', 'text', array(
			'required'	=> true,
			'label' => 'ENTER_LOGIN_OF_MERGING_ACCOUNT'
		));
        
        $builder->add('password', 'password', array(
			'required'	=> true,
			'label'  => 'ENTER_PASSWORD'
		));
	}
	
    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
		$resolver->setDefaults(
				array(
						'translation_domain' => 'PROFILE'
				)
		);
    }

    /**
     * @return string 
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}