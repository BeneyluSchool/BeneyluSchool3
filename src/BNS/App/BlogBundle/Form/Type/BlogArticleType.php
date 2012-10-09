<?php

namespace BNS\App\BlogBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\BlogArticlePeer;

class BlogArticleType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('categories_list_id', 'hidden', array('required' => false));
        $builder->add('title');
        $builder->add('content', 'textarea');
		
		$statuses = array_flip(BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS));
		$statuses['PROGRAMMED'] = 'programmed';
		
        $builder->add('status', 'choice', array(
			'choices'	=> $statuses,
			'expanded'	=> true
		));
        $builder->add('programmation_day', 'date', array(
			'widget'	=> 'single_text',
			'format'	=> 'dd/MM/yyyy'
		));
        $builder->add('programmation_time', 'time', array(
			'input'		=> 'string',
			'widget'	=> 'choice',
			'minutes'	=> array(
				'00'	=> '00',
				'15'	=> '15',
				'30'	=> '30',
				'45'	=> '45'
			)
		));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\BlogBundle\Form\Model\BlogArticleFormModel',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'blog_article_form';
    }
}