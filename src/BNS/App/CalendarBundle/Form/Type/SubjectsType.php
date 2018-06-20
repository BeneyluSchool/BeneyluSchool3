<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 08/02/2018
 * Time: 17:22
 */

namespace BNS\App\CalendarBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class SubjectsType extends AbstractType
{

    protected $myGroups;

    /**
     * SubjectsType constructor.
     * @param $myGroups
     */
    public function __construct($myGroups)
    {
        $this->myGroups = $myGroups;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Titre
        $builder->add('title', 'text');

        // Groupe concerné par l'événement
        foreach ($this->myGroups as $group) {
            $groups[$group->getId()] = $group->getLabel();
        }

        $builder->add('agendaId', 'choice', array(
            'choices' => $groups,
            'expanded' => true,
            'required' => true,
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
       return 'calendar_subjects_form';
    }
}