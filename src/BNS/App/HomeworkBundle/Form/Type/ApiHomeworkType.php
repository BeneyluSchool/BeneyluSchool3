<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class ApiHomeworkType
 *
 * @package BNS\App\HomeworkBundle\Form\Type
 */
class ApiHomeworkType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('date', 'date', [
            'widget' => 'single_text',
        ]);
        $builder->add('name', 'text');
        $builder->add('description', 'textarea');
        $builder->add('helptext', 'textarea');

        $builder->add('recurrence_type', 'choice', [
            'choices' => array(
                'ONCE' => 'CHOICE_ONCE',
                'EVERY_WEEK' => 'CHOICE_EVERY_WEEK',
                'EVERY_TWO_WEEKS' => 'CHOICE_EVERY_TWO_WEEK',
                'EVERY_MONTH' => 'CHOICE_EVERY_MONTH',
            ),
        ]);
        $builder->add('recurrence_end_date', 'date', [
            'widget' => 'single_text',
        ]);

        $builder->add('scheduled_publication');
        $builder->add('publication_date', 'date', [
            'widget' => 'single_text',
        ]);

        $builder->add('has_locker');

        $builder->add('resource-joined', 'hidden', array(
            'mapped' => false,
        ));

        $builder->add('groups', 'model', array(
            'class' => 'BNS\\App\\CoreBundle\\Model\\Group',
            'multiple' => true,
            'expanded' => true,
            'query' => GroupQuery::create()->filterById($options['groupIds']),
        ));

        $builder->add('users', 'model', [
            'class' => 'BNS\\App\\CoreBundle\\Model\\User',
            'multiple' => true,
            'expanded' => true,
            'query' => UserQuery::create()->filterById($options['userIds'])
        ]);

        $builder->add('homework_subject', 'model', array(
            'class' => 'BNS\\App\\HomeworkBundle\\Model\\HomeworkSubject',
            'choices_as_values' => true,
            'query' => HomeworkSubjectQuery::create()->filterById($options['subjectIds']),
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'BNS\\App\HomeworkBundle\\Model\\Homework',
            'translation_domain' => 'HOMEWORK',
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
        return 'api_homework';
    }

}
