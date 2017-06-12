<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Bridge\Twig\Extension\TranslationExtension;

class HomeworkPreferencesType extends AbstractType
{


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('group_id', 'hidden');
        $builder->add('days', 'choice', array(
            'choices' => array(
                'MO' => 'CHOICE_MONDAY',
                'TU' => 'CHOICE_TUESDAY',
                'WE' => 'CHOICE_WEDNESDAY',
                'TH' => 'CHOICE_THURSDAY',
                'FR' => 'CHOICE_FRIDAY',
                'SA' => 'CHOICE_SATURDAY',
                'SU' => 'CHOICE_SUNDAY',
            ),
            'choice_translation_domain' => 'HOMEWORK',
            'multiple' => true,
            'expanded' => true,
            'label' => 'VISIBLE_DAYS_CHOICE',
        ));
        $builder->add('activate_validation', 'hidden', array(
            'required' => false,
            'label' => 'ENABLE_WORK_VALIDATION',
        ));
        $builder->add('show_tasks_done', 'hidden', array(
            'required' => false,
            'label' => 'SHOW_FINISHED_WORKS',
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\HomeworkBundle\Model\HomeworkPreferences',
            'translation_domain' => 'HOMEWORK'
        ));
    }

    public function getName()
    {
        return "homeworkpreferences";
    }

}
