<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class UserType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('first_name','text',array('required' => true));
		$builder->add('last_name','text',array('required' => true));
		$builder->add('birthday', 'birthday', array(
			'widget' => 'choice',
			'years' => range(1920,2012),
			'format' => "dd/MM/yyyy",
		));
		$builder->add('email','email',array('required' => true));
		$builder->add('lang','locale',array('required' => true));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\CoreBundle\Model\User',
        ));
    }
	
	public function getName()
	{
		return 'user';
	}
}
