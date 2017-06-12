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
		$date_format = "dd/MM/yyyy";
		$builder->add('id', 'hidden', array('required' => false));
		$builder->add('title', null ,array('label'=>'PLACEHOLDER_TITLE_MESSAGE',));
		$builder->add('date', 'date', array(
		    'input' => 'datetime',
		    'widget' => 'single_text',
			'proxy' => true,
		    'attr' => array('class' => 'jq-date', 'placeholder' => 'DATE_PLACEHOLDER'),
		    'required' => true,
		));
		$builder->add('content', 'textarea', array(
			'parse_media' => true,
		));
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\CoreBundle\Model\LiaisonBook',
            'translation_domain' => 'LIAISONBOOK'
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
