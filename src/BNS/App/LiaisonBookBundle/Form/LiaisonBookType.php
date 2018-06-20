<?php

namespace BNS\App\LiaisonBookBundle\Form;

use BNS\App\CoreBundle\Model\UserQuery;
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
     * @var array
     */
    private $userIds;
	/**
	 * @param type $editionMode
	 */
	public function __construct($editionMode = false, $userIds = array())
	{
		$this->editionMode = $editionMode;
		$this->userIds = $userIds;
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
		    'attr' => array('class' => 'jq-date', 'placeholder' => 'DATE_PLACEHOLDER'),
		    'required' => true,
		));
        $builder->add('publicationDate', 'date', array(
            'label' => 'TITLE_PUBLICATION_DATE',
            'input' => 'datetime',
            'widget' => 'single_text',
            'attr' => array('class' => 'jq-date', 'placeholder' => 'DATE_PLACEHOLDER' ,'bns-feature-flag' => '"liaison_book_schedule"'),
            'required' => false,
        ));
		$builder->add('content', 'textarea', array(
			'parse_media' => true,
		));

		$builder->add('addresseds', 'model', [
            'class' => 'BNS\\App\\CoreBundle\\Model\\User',
            'multiple' => true,
            'expanded' => true,
            'query' => UserQuery::create()->filterById($this->userIds, \Criteria::IN)
           ]);
		$builder->add('individualized', 'checkbox', array('label'=>'CHECKBOX_INDIVIDUALIZE',));
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
