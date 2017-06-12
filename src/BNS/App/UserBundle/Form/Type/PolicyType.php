<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class PolicyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('policy', 'checkbox', array(
                'label' => "LABEL_ACCEPT_CHARTER",
                'constraints' => array(
                        new NotBlank(array('message' => ($options['is_child'] ? 'INVALID_CHILD_ACCEPT_CHARTER' : 'INVALID_ADULT_ACCEPT_CHARTER'))),
                )
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
                'is_child' => false,
                'translation_domain' => 'USER'
        ));
    }

    public function getName()
    {
        return 'form_policy_type';
    }
}
