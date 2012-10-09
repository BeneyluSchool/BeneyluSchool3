<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupTypeDataChoiceI18nType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		 $builder->add('lang', 'locale');
        $builder->add('label');
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\GroupTypeDataChoiceI18n',
        ));
    }

    public function getName()
    {
        return 'group_type_data_choice_i18n';
    }
}