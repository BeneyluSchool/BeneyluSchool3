<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EmailTemplateType extends AbstractType
{
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder->add('vars','text', array('required' => true));
		
		$builder->add('email_template_i18ns', 'collection', array(
            'type'          => new \BNS\App\CoreBundle\Form\Type\EmailTemplateI18nType(),
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
            'data_class' => 'BNS\App\CoreBundle\Model\EmailTemplate',
        ));
    }

    public function getName()
    {
        return 'email_template';
    }
}
