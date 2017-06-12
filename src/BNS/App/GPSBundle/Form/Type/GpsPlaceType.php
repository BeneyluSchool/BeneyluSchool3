<?php

namespace BNS\App\GPSBundle\Form\Type;

use BNS\App\GPSBundle\Model\GpsCategoryQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class GpsPlaceType extends AbstractType
{
	const FORM_NAME = 'gps_place_form';

	protected $categoriesRoute;

	public function __construct($categoriesRoute = '')
	{
		$this->categoriesRoute = $categoriesRoute;
	}

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		// Titre
		$builder->add('label', 'text',array('label'=> 'PLACEHOLDER_PLACE_NAME'));
		$builder->add('address', 'text',array('label' => 'PLACEHOLDER_PLACE_ADDRESS'));
		$builder->add('description', 'textarea',array('label' => 'PLACEHOLDER_PLACE_DESCRIPTION', 'required' => 'false'));
		$builder->add('id', 'hidden');
		$builder->add('gps_category', 'model', array(
            'class' => 'BNS\\App\\GPSBundle\\Model\\GpsCategory',
            'query' => GpsCategoryQuery::create()->filterByGroupId($options['group_id'], \Criteria::EQUAL)->orderByOrder(),
            'label' => 'CHOOSE_CATEGORY',
            'choice_label' => 'label',
            'by_reference' => false,
            'choices_as_values' => true,
            'expanded' => true,
            'multiple' => false,
            'proxy' => true,
            'constraints' => array(new NotBlank(array(
                'message' => 'INVALID_CHOICE_A_CATEGORY'
            ))),
            'create' => $this->categoriesRoute,
        ));
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\GPSBundle\Model\GpsPlace',
            'translation_domain' => 'GPS',
            'group_id' => '',
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
