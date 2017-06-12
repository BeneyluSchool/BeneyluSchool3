<?php

namespace BNS\App\UserBundle\Form\Type;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class PasswordChangeApiType
 *
 * @package BNS\App\UserBundle\Form\Type
 */
class PasswordChangeApiType extends AbstractType
{

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // current password
        $builder->add('password', 'password', [
           'constraints' => [
               new NotBlank(),
           ] ,
        ]);

        // new password
        $builder->add('plain_password', 'password', [
            'constraints' => [
                new NotBlank(),
            ],
        ]);

        $builder->add('redirect', 'checkbox');
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'password_change';
    }

}
