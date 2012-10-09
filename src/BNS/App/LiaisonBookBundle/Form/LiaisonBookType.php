<?php

namespace BNS\App\LiaisonBookBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class LiaisonBookType extends AbstractType
{

	/**
	 * @var boolean 
	 */
	private $editionMode;

	/**
	 * @param type $editionMode 
	 */
	public function __construct($editionMode = false)
	{
		$this->editionMode = $editionMode;
	}
	
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('id', 'hidden', array('required' => false));
		$builder->add('title');
		$builder->add('content');
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\CoreBundle\Model\LiaisonBook',
        ));
    }

	/**
	 * @return string 
	 */
	public function getName()
	{
		return 'liaisonbook_form';
	}
}