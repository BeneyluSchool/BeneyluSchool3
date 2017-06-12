<?php

namespace BNS\App\RegistrationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class UserRegistrationType extends AbstractType
{
    public function __construct($email = null)
    {
        $this->email = $email;
    }

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first_name', 'text', array('required' => true));
        $builder->add('last_name', 'text', array('required' => true));
        $builder->add('email', 'text', array('required' => true, 'data' =>  $this->email));
    }

	/**
	 * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\RegistrationBundle\Form\Model\UserFormModel',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'user_registration_form';
    }
}