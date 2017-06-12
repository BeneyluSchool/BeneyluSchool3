<?php

namespace BNS\App\CoreBundle\Form\Type;

use BNS\App\CoreBundle\Model\RankQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ModuleType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $ranks = RankQuery::create()->find()->getPrimaryKeys();

        $builder->add('uniqueName',null,array('label' => "LABEL_UNIQUE_NAME",'required' => true));
        $builder->add('isContextable','checkbox',array('label' => 'LABEL_CONTEXTABLE','required' => false));
        $builder->add('bundleName',null,array('label' => "LABEL_BUNDLE",'required' => true));
        $builder->add('default_pupil_rank', 'choice', ['choices' => array_combine($ranks, $ranks)]);
        $builder->add('default_parent_rank', 'choice', ['choices' => array_combine($ranks, $ranks)]);
        $builder->add('default_other_rank', 'choice', ['choices' => array_combine($ranks, $ranks)]);
    }

	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Module',
            'translation_domain' => 'CORE'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'module';
    }
}
