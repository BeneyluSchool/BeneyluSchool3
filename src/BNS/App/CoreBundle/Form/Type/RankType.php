<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class RankType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('uniqueName',null,array('label' => "LABEL_UNIQUE_NAME",'required' => true));
		$builder->add('module', 'model', array(
            'class' => 'BNS\App\CoreBundle\Model\module',
        ));
    }

	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Rank',
            'translation_domain' => 'CORE'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'rank';
    }
}
