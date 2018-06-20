<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class EmailTemplateI18nType extends AbstractType
{
	 public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$builder->add('label','text',array('required' => true));
		$builder->add('subject','text',array('required' => true));
		$builder->add('html_body',null,array('required' => true));
		$builder->add('plain_body',null,array('required' => true));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CoreBundle\Model\EmailTemplateI18n'
        ));
    }

    public function getName()
    {
        return 'email_template_i18n';
    }
}
