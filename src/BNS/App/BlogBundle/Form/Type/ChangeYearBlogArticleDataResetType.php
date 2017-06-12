<?php

namespace BNS\App\BlogBundle\Form\Type;

use BNS\App\BlogBundle\DataReset\ChangeYearBlogArticleDataReset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearBlogArticleDataResetType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		$builder->add('choice', 'choice', array(
			'required'	     => true,
			'choices'	     => ChangeYearBlogArticleDataReset::getChoices(),
			'empty_value'    => 'PLEASE_CHOICE',
            'error_bubbling' => true
		));
	}

	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\BlogBundle\DataReset\ChangeYearBlogArticleDataReset',
            'translation_domain' => 'BLOG'
        ));
    }

	/**
	 * @return string
	 */
	public function getName()
	{
		return 'blog_article_data_reset_form';
	}
}
