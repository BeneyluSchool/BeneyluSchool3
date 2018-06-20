<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\FormBuilderInterface;
use \Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageNewsType extends AbstractType
{
	/**
	 * @var boolean
	 */
	private $isAdmin;

	/**
	 * @param boolean $isAdmin
	 */
	public function __construct($isAdmin)
	{
		$this->isAdmin = $isAdmin;
	}

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text', array('required' => true));
        $builder->add('content', 'textarea', array('required' => true));

        if ($this->isAdmin) {
			$statuses = MiniSitePageNewsPeer::getValueSet(MiniSitePageNewsPeer::STATUS);
			$builder->add('status', 'choice', array(
				'choices'	=> array_combine($statuses, $statuses),
				'expanded'	=> true,
                'label' => 'NEWS_STATUS',
                'attr' => [ 'bns-status' => '' ],
                'proxy' => true
			));
		}
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\MiniSiteBundle\Model\MiniSitePageNews',
            'translation_domain' => 'MINISITE',
        ));
    }

	/**
	 * @return string
	 */
    public function getName()
    {
        return 'mini_site_page_news_form';
    }
}
