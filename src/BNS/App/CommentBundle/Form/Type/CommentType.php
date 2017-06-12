<?php

namespace BNS\App\CommentBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CommentType extends AbstractType
{
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('id', 'hidden');
        $builder->add('object_id', 'hidden');
        $builder->add('author_id', 'hidden');
        $builder->add('content', 'textarea', array(
            'attr' => array('rows' => '5'),
            'label' => ' '
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CommentBundle\Form\Model\CommentForm',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'comment_form';
    }
}