<?php

namespace BNS\App\HomeworkBundle\Form\Type;

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
        $date_format = ($this->userLocale == 'fr' || $this->userLocale == 'fr' ? 'dd/MM/yyyy' : 'MM-dd-yyyy');

        $builder->add('id', 'hidden');
        $builder->add('name', 'text');
        $builder->add('date', 'date', array(
            'input' => 'datetime',
            'widget' => 'single_text',
            'format' => $date_format,
            'attr' => array('class' => 'jq-date'),
        ));
        $builder->add('description', 'textarea');
        $builder->add('helptext', 'textarea', array(
            'required' => false,
        ));
        $builder->add('recurrence_end_date', 'date', array(
            'input' => 'datetime',
            'widget' => 'single_text',
            'format' => $date_format,
            'attr' => array('class' => 'jq-date'),
            'required' => false,
        ));

        $builder->add('recurrence_type', 'choice', array(
            'choices' => array(
                'ONCE' => 'Une fois',
                'EVERY_WEEK' => 'Toutes les semaines',
                'EVERY_TWO_WEEKS' => 'Toutes les deux semaines',
                'EVERY_MONTH' => 'Tous les mois',
            ),
        ));

        $builder->add('recurrence_days', 'choice', array(
            'choices' => array(
                'SU' => 'Dimanche',
                'MO' => 'Lundi',
                'TU' => 'Mardi',
                'WE' => 'Mercredi',
                'TH' => 'Jeudi',
                'FR' => 'Vendredi',
                'SA' => 'Samedi',
            ),
            'required' => false,
            'multiple' => true,
            'expanded' => true,
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
            'choices' => $this->groups,
        ));
        
        // Ajout d'un champ caché pour savoir si l'utilisateur
        // souhaite creer un autre devoir apres celui-ci
        $builder->add('createAnother', 'hidden', array('property_path' => false, 'data' => 'false',));
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\HomeworkBundle\Model\Homework',
        ));
    }

    public function getName()
    {
        return "homework_form";
    }

}