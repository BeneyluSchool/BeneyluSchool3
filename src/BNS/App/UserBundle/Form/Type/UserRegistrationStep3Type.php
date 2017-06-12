<?php
namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserRegistrationStep3Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('label', 'text', array(
                'label' => "PLACEHOLDER_SCHOOL_NAME",
                'constraints' => array(
                        new NotBlank(array('message' => 'INVALID_SCHOOL_NAME')),
                )
        ));

        $builder->add('zipcode', 'text', array(
            'label' => "PLACEHOLDER_ZIP_CODE",
            'constraints' => array(
                new NotBlank(array('message' => 'INVALID_ZIP_CODE')),

//                TODO find a global rule that works for all code
//                new Regex(array(
//                    'pattern' => '/^((0[1-9])|([1-8][0-9])|(9[0-8])|(2A)|(2B))[0-9]{3}$/i', // only french codes
//                    'message' => 'INVALID_ZIP_CODE'
//                )),
            )
        ));

        $builder->add('city', 'text', array(
            'label' => "PLACEHOLDER_CITY",
            'constraints' => array(
                new NotBlank(array('message' => 'INVALID_CITY')),
            )
        ));

        if ($options['with_address']) {
            $builder->add('address', 'textarea', []);
        }

        $builder->add('skip', 'submit');
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'translation_domain' => 'USER',
            'with_address' => false,
        ));
    }

    public function getName()
    {
        return 'form_user_registration_step_1';
    }
}
