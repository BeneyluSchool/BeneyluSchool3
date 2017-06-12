<?php

namespace BNS\App\SchoolBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EditSchoolType extends AbstractType
{
    const FORM_NAME = 'edit_school_form';

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('home_message', 'textarea', [
            'label' => "FORM_WELCOME_MESSAGE"
        ]);

        $builder->add('address', 'text', [
            'label' => 'LABEL_ADDRESS'
        ]);

        $builder->add('city', 'text', [
            'label' => 'LABEL_CITY'
        ]);

        $builder->add('zipcode', 'text', [
            'label' => 'LABEL_ZIPCODE'
        ]);

        $builder->add('country', 'available_country', [
            'empty_value' => 'LABEL_COUNTRY',
            'constraints' => [
                new NotBlank(),
            ],
            'label' => 'LABEL_COUNTRY'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\App\SchoolBundle\Form\Model\EditSchoolFormModel',
            'translation_domain' => 'SCHOOL'
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}
