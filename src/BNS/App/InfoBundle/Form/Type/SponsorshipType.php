<?php

namespace BNS\App\InfoBundle\Form\Type;

use BNS\App\InfoBundle\Model\AnnouncementPeer;
use BNS\App\InfoBundle\Model\AnnouncementUser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SponsorshipType extends AbstractType
{
    public function __construct()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('email','email',array('label' => "LABEL_TEACHER_REFERAL",'required' => true, 'attr' => array('placeholder' => "LABEL_TEACHER_REFERAL", 'class' => 'large')));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\InfoBundle\Model\Sponsorship',
            'translation_domain' => 'INFO'
        ));
    }
	
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'sponsorship';
    }
}
