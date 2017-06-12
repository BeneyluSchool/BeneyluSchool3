<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 *
 * @author brian.clozel@atos.net
 */
class HomeworkType extends AbstractType
{

    private $subjects;
    private $groups;
    private $userLocale;

    public function __construct($subjects, $groups, $locale)
    {
        $this->subjects = $subjects;
        $this->groups = $groups;
        $this->userLocale = $locale;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $date_format = "dd/MM/yyyy";

        $builder->add('id', 'hidden');
        $builder->add('name', 'text');
        $builder->add('date', 'date', array(
            'input' => 'datetime',
            'widget' => 'single_text',
            'format' => $date_format,
            'attr' => array('class' => 'jq-date', 'placeholder' => 'PLACEHOLDER_DAY_MONTH_YEAR'),
        ));
        $builder->add('description', 'textarea');
        $builder->add('helptext', 'textarea', array(
            'required' => false,
        ));
        $builder->add('recurrence_end_date', 'date', array(
            'input' => 'datetime',
            'widget' => 'single_text',
            'format' => $date_format,
            'attr' => array('class' => 'jq-date', 'placeholder' => 'PLACEHOLDER_DAY_MONTH_YEAR'),
            'required' => false,
        ));

        $builder->add('recurrence_type', 'choice', array(
            'choices' => array(
                'ONCE' => 'CHOICE_ONCE',
                'EVERY_WEEK' => 'CHOICE_EVERY_WEEK',
                'EVERY_TWO_WEEKS' => 'CHOICE_EVERY_TWO_WEEK',
                'EVERY_MONTH' => 'CHOICE_EVERY_MONTH',
            ),
        ));

        $builder->add('recurrence_days', 'choice', array(
            'choices' => array(
                'SU' => 'CHOICE_SUNDAY',
                'MO' => 'CHOICE_MONDAY',
                'TU' => 'CHOICE_TUESDAY',
                'WE' => 'CHOICE_WEDNESDAY',
                'TH' => 'CHOICE_THURSDAY',
                'FR' => 'CHOICE_FRIDAY',
                'SA' => 'CHOICE_SATURDAY',
            ),
            'required' => false,
            'multiple' => true,
            'expanded' => true,
        ));

        $builder->add('has_locker', null, array(
            'label' => 'LABEL_ASSOCIATE_LOCKER_TO_WORK',
        ));

        $subject_list = array();
        foreach ($this->subjects as $subject) {
            if (!$subject->isRoot()) {
                if($subject->getParent()->isRoot())
                {
                    $subject_list[$subject->getId()] = $subject->getName();
                }
                else
                {
                    $subject_list[$subject->getId()] = "<ol><li>".$subject->getName()."</li></ol>";
                }
            }
        }
        $builder->add('subject_id', 'choice', array(
            'choices' => $subject_list,
            'multiple' => false,
            'expanded' => true,
            'required' => true
        ));

        $builder->add('groups', 'model', array(
            'class' => 'BNS\App\CoreBundle\Model\Group',
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'query' => GroupQuery::create()->filterById($this->groups->getPrimaryKeys()),
        ));

        // Ajout d'un champ caché pour savoir si l'utilisateur
        // souhaite creer un autre devoir apres celui-ci
        $builder->add('createAnother', 'hidden', array('mapped' => false, 'data' => 'false',));
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\HomeworkBundle\Model\Homework',
            'translation_domain' => 'HOMEWORK'
        ));
    }

    public function getName()
    {
        return "homework_form";
    }

}
