<?php

namespace BNS\App\HomeworkBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class HomeworkPreferencesType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('group_id', 'hidden');
        $builder->add('days', 'choice', array(
            'choices' => array(
                'MO' => 'Lundi',
                'TU' => 'Mardi',
                'WE' => 'Mercredi',
                'TH' => 'Jeudi',
                'FR' => 'Vendredi',
                'SA' => 'Samedi',
                //'SU' => 'Dimanche',
            ),
            'multiple' => true,
            'expanded' => true,
            'label' => 'Choix des jours visibles',
        ));
        $builder->add('activate_validation', 'checkbox', array(
            'required' => false,
            'label' => 'Activer la validation des travaux',
        ));
        $builder->add('show_tasks_done', 'checkbox', array(
            'required' => false,
            'label' => 'Afficher les travaux terminés',
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\HomeworkBundle\Model\HomeworkPreferences',
        ));
    }

    public function getName()
    {
        return "homeworkpreferences";
    }

}
