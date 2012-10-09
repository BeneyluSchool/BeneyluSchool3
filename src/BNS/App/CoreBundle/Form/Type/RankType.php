<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Form\Type\RankI18nType;

class RankType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('uniqueName',null,array('label' => "Nom unique ",'required' => true));
		$builder->add('rank_i18ns', 'collection', array(
            'type'          => new RankI18nType(),
            'allow_add'     => true,
            'allow_delete'  => false,
            'by_reference'  => true
        ));
		$builder->add('module', 'model', array(
            'class' => 'BNS\App\CoreBundle\Model\module',
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Rank',
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
