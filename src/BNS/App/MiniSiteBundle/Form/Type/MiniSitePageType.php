<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Model\MiniSitePagePeer;

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
        $builder->add('type', 'choice', array(
			'required'	=> false,
			'choices'	=> array_flip(MiniSitePagePeer::getValueSet(MiniSitePagePeer::TYPE))
		));
        $builder->add('is_home', 'checkbox', array('required' => false));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\MiniSitePage',
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