<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Class ApiHomeworkCreateType
 *
 * @package BNS\App\HomeworkBundle\Form\Type
 */
class ApiHomeworkCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('homeworks', 'collection', [
            'type' => new ApiHomeworkType(),
            'allow_add' => true,
            'allow_delete' => true,
            'constraints' => [
                new Count(['min' => 1]),
            ],
            'options' => [
                'userIds' => $options['userIds'],
                'groupIds' => $options['groupIds'],
                'subjectIds' => $options['subjectIds'],

            ]
        ]);
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
       $resolver->setDefaults([
           'userIds' => [],
           'groupIds' => [],
           'subjectIds' => [],
       ]);
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'api_homework_create';
    }

}
