<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupTypeDataTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('default_value', 'text');
		$builder->add('group_type_data_template_i18ns', 'collection', array(
            'type'          => new \BNS\App\CoreBundle\Form\Type\GroupTypeDataTemplateI18nType(),
            'allow_add'     => false,
            'allow_delete'  => false,
            'by_reference'  => false
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\GroupTypeDataTemplate',
        ));
    }

    public function getName()
    {
        return 'group_type_data_template';
    }
}