<?php

namespace BNS\App\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ApiUserType
 *
 * @package BNS\App\UserBundle\Form\Type
 */
class ApiUserType extends AbstractType
{

    /**
     * @inheritDoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('first_name', null, [
            'constraints' => [
                new NotBlank(),
            ],
        ]);
        $builder->add('last_name', null, [
            'constraints' => [
                new NotBlank(),
            ],
        ]);
        $builder->add('ine', null, [
            'constraints' => [
                new NotBlank(),
                new Length([
                    'min' => 11,
                    'max' => 11,
                ]),
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\CoreBundle\Model\User',
        ]);
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'user';
    }

}
