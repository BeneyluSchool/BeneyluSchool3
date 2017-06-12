<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class CGUType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('cgu_validation', 'checkbox', array(
            'label' => "LABEL_ACCEPT_CGU",
            'constraints' => array(
                new NotBlank(array('message' => 'INVALID_ACCEPT_CGU')),
            )
        ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'USER'
        ));
    }

    public function getName()
    {
        return 'form_CGU_type';
    }
}
