<?php

namespace BNS\App\InfoBundle\Form\Type;

use BNS\App\InfoBundle\Model\AnnouncementPeer;
use BNS\App\InfoBundle\Model\AnnouncementUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ContactType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('description','textarea',array('label' => 'LABEL_YOUR_MESSAGE','required' => true, 'attr' => array('placeholder' => "PLACEHOLDER_ENTER_MESSAGE")));
    }

	/**
	 * @param OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\InfoBundle\Model\Contact',
            'translation_domain' => 'INFO'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'contact';
    }
}
