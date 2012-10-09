<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BNS\App\CoreBundle\Form\Type\PermissionI18nType;

class PermissionType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('uniqueName',null,array('label' => "Nom unique ",'required' => true));
		$builder->add('permission_i18ns', 'collection', array(
            'type'          => new PermissionI18nType(),
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
            'data_class' => 'BNS\App\CoreBundle\Model\Permission',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'permission';
    }
}
