<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\FormBuilderInterface;
use \Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text', array('required' => true));

		if ($options['is_edition']) {
			$builder->add('id', 'hidden', array('required' => true));
			$builder->add('is_home', 'checkbox', array('required' => false));
		}
		else {
			$builder->add('type', 'choice', array(
				'required' => false,
				'choices' => array(
					'TEXT' => 'STATIC_PAGE',
					'NEWS' => 'NEWS_PAGE'
				),
				'empty_value' => false
			));
		}
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\MiniSiteBundle\Model\MiniSitePage',
			'is_edition' => false,
            'translation_domain' => 'MINISITE'
        ));
    }

	/**
	 * @return string
	 */
    public function getName()
    {
        return 'mini_site_page_form';
    }
}
