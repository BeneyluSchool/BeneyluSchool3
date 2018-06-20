<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use JMS\TranslationBundle\Annotation\Ignore;

class UserType extends AbstractType
{
	public function __construct($showUsername = false)
    {
        $this->showUsername = $showUsername;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
	{



		$builder->add('first_name','text',array('required' => true));
		$builder->add('last_name','text',array('required' => true));

        /** @Ignore */
		$builder->add('birthday', 'birthday', array(
			'widget' => 'choice',
			'years' => range(1945,2012),
			'format' => "dd/MM/yyyy",
			'empty_value' =>  '',
			'required' => ''
		));
		$builder->add('email','email',array('required' => false));

        if ($this->showUsername === true) {
            $builder->add('login','text',array('required' => true));
        }

        $builder->add('lang', 'text', array(
           'label' => 'LABEL_LANG',
            'required' => true
        ));

        $builder->add('gender', 'choice', array(
            'choices'   => array('M' => "CHOICE_MAN", 'F' => "CHOICE_WOMAN"),
            'required'  => true,
            'expanded' => true,
            'multiple' => false
        ));

		//$builder->add('lang','locale',array('required' => true));
		/* bns-9661 Commenté car pas Nantes
		$builder->add('expires_at', 'date', array(
			'widget' => 'choice',
			'years' => range(2012,2050),
			'format' => "dd/MM/yyyy",
			'empty_value' => '',
			'required' => ''
		));
		/* Fin bns-9661 */
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\CoreBundle\Model\User',
            'translation_domain' => 'CORE'
        ));
    }

	public function getName()
	{
		return 'user';
	}
}
