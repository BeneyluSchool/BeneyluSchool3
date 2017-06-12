<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class GroupSimpleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder->add('label','text',array('label' => 'LABEL_NAME'));
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\Group',
            'translation_domain' => 'CORE'
        ));
    }

    public function getName()
    {
        return 'group_type';
    }
}