<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageTextType extends AbstractType
{
	/**
	 * @var boolean
	 */
	private $hasAdminRight;

	/**
	 * @param boolean $hasAdminRight
	 */
	public function __construct($hasAdminRight = false)
	{
		$this->hasAdminRight = $hasAdminRight;
	}

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('draft_title', 'text', array('required' => true));
        $builder->add('draft_content', 'textarea', array('required' => true));

		if ($this->hasAdminRight) {
			$statuses = MiniSitePageTextPeer::getValueSet(MiniSitePageTextPeer::STATUS);
			$builder->add('status', 'choice', array(
				'choices'	=> array_combine($statuses, $statuses),
				'expanded'	=> true,
                'label' => 'PAGE_STATUS',
                'attr' => [ 'bns-status' => '' ],
                'proxy' => true
                //'choices_as_values' => true,

			));
		}
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\MiniSiteBundle\Model\MiniSitePageText',
            'translation_domain' => 'MINISITE',
        ));
    }

	/**
	 * @return string
	 */
    public function getName()
    {
        return 'mini_site_page_text_form';
    }
}
