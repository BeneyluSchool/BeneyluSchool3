<?php

namespace BNS\App\BlogBundle\Form\Type;

use BNS\App\CoreBundle\Translation\TranslatorTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BlogType extends AbstractType
{

    use TranslatorTrait;

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $translator = $this->getTranslator();

        $builder->add('title', 'text', array(
            'required' => true,
            'label' => $translator->trans('TITLE_BLOG', [], 'BLOG'),
        ));
        $builder->add('description', 'textarea', array('required' => false));
        $builder->add('avatar_resource_id', 'hidden', array('required' => false));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Blog',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'blog_form';
    }
}
