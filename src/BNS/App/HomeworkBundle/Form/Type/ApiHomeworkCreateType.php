<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;

/**
 * Class ApiHomeworkCreateType
 *
 * @package BNS\App\HomeworkBundle\Form\Type
 */
class ApiHomeworkCreateType extends AbstractType
{

    protected $groups;

    protected $subjects;

    public function __construct($groups, $subjects = [])
    {
        $this->groups = $groups;
        $this->subjects = $subjects;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('homeworks', 'collection', [
            'type' => new ApiHomeworkType($this->groups, $this->subjects),
            'allow_add' => true,
            'allow_delete' => true,
            'constraints' => [
                new Count(['min' => 1]),
            ],
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
