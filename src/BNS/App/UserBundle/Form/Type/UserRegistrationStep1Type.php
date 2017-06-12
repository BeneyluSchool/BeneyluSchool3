<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserRegistrationStep1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first_name', 'text', array(
                'label' => 'LABEL_FIRST_NAME',
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_EMPTY_FIRST_NAME')),
                )
        ));

        $builder->add('last_name', 'text', array(
                'label' => 'LABEL_LAST_NAME',
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_EMPTY_LAST_NAME')),
                )
        ));

        $builder->add('civility', 'choice', array(
            'choices'   => array('M' => 'LABEL_GENDER_MALE', 'F' => 'LABEL_GENDER_FEMALE'),
            'expanded' => true,
            'attr' => ['class' => 'layout-gt-sm-row layout-wrap layout-margin'],
            'label' => 'LABEL_GENDER_ARE_YOU',
            'choice_attr' => function (){
                return ['class' => 'flex-gt-sm-25'];
            }
        ));

        $builder->add('country', 'available_country', array(
            'empty_value' => 'LABEL_COUNTRY',
            'attr' => array(
                'placeholder' => 'LABEL_COUNTRY',
            ),
            'constraints' => array(
                new NotBlank(),
            ),
            'label' => 'LABEL_WHERE_DO_YOU_LIVE'
        ));

        $builder->add('lang', 'available_locale', array(
            'constraints' => array(
                new NotBlank(array('message' => 'INVALID_LOCALE')),
            ),
            'label' => 'LABEL_LANGUAGE_YOU_SPEAK',
        ));

        if ($options['cgu_enabled']) {
            $builder->add('cgu', 'checkbox', [
                'mapped' => true,
                'required' => true,
                'constraints' => array(
                    new NotBlank(array('message' => 'INVALID_ACCEPT_CGU')),
                )
            ]);
        }

    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'USER',
            'cgu_enabled' => false
        ));
    }

    public function getName()
    {
        return 'form_user_registration_step_1';
    }
}
