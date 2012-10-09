<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Form\Type\ModuleI18nType;

class ModuleType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('uniqueName',null,array('label' => "Nom unique",'required' => true));
        $builder->add('isContextable','checkbox',array('label' => 'Contextable','required' => false));
        $builder->add('isActivable','checkbox',array('label' => 'Activable','required' => false));
        $builder->add('bundleName',null,array('label' => "Bundle",'required' => true));
        $builder->add('defaultPupilRank','model', array('class' => 'BNS\App\CoreBundle\Model\Rank','label' => "Rang élève par défaut",'required' => false));
        $builder->add('defaultParentRank','model', array('class' => 'BNS\App\CoreBundle\Model\Rank','label' => "Rang parent par défaut",'required' => false));
        $builder->add('defaultOtherRank','model', array('class' => 'BNS\App\CoreBundle\Model\Rank','label' => "Rang autres par défaut",'required' => false));
		$builder->add('module_i18ns', 'collection', array(
            'type'          => new ModuleI18nType(),
            'allow_add'     => true,
            'allow_delete'  => false,
            'by_reference'  => true
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Module',
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
