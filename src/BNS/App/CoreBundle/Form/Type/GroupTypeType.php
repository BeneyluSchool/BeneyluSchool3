<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('centralize','checkbox', array('required' => false));
		$builder->add('simulate_role','checkbox', array('required' => false));
		$builder->add('type','text', array('required' => true));
		$builder->add('group_type_i18ns', 'collection', array(
            'type'          => new \BNS\App\CoreBundle\Form\Type\GroupTypeI18nType(),
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
            'data_class' => 'BNS\App\CoreBundle\Model\GroupType'
        ));
    }

    public function getName()
    {
        return 'group_type';
    }
}