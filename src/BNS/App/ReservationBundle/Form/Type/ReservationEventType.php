<?php

namespace BNS\App\ReservationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ReservationEventType extends AbstractType
{
    const FORM_NAME = 'reservation_event_form';

    private $userLocale;

    public function __construct($locale)
    {
        $this->userLocale = $locale;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('reservationItem', 'model', array(
                'class' => 'BNS\App\ReservationBundle\Model\ReservationItem',
                'group_by' => 'type',
                'empty_value' => 'Choisissez votre réservation'
                ));

        $date_format = ($this->userLocale == 'fr' || $this->userLocale == 'fr' ? 'dd/MM/yyyy' : 'MM-dd-yyyy');
        // Date et Horaire de début
        $builder->add('dateStart', 'date', array(
                'input' => 'string',
                'widget' => 'single_text',
                'format' => $date_format,
                'required' => true,
                ));

        $builder->add('timeStart', 'text', array(
                'required' => false,
                ));

        // Date et Horaire de fin
        $builder->add('dateEnd', 'date', array(
                'input' => 'string',
                'widget' => 'single_text',
                'format' => $date_format,
                'required' => true,
                ));

        $builder->add('timeEnd', 'text', array('required' => false,));

        // Toute la journée ?
        $builder->add('isAllDay', 'checkbox', array('required' => false,));

        // Description (Facultatif)
        $builder->add('description', 'textarea', array('required' => false,));

        // Récurrence ?
        $builder->add('isRecurring', 'checkbox', array('required' => false,));

        $choices = array(
                'DAILY' => 'Tous les jours',
                'WEEKLY' => 'Toutes les semaines',
                'MONTHLY' => 'Tous les mois',
                'YEARLY' => 'Tous les ans',
                );
        // Récurrence Type
        $builder->add('recurringType', 'choice', array(
                'choices' => $choices, 'required' => false,
                'label' => 'Quelle type d\'ocurrence',
                'empty_value' => 'Type d\'occurrence...',
                'empty_data' => null
                ));

        // Nombre récurrence
        $builder->add('recurringCount', 'text', array(
                'required' => false,
                'label' => 'Durant :',
                ));

        // ou une date de fin
        $builder->add('recurringEndDate', 'date', array(
                'widget' => 'single_text',
                'format' => $date_format,
                'required' => false,
                'label' => 'Ou une date de fin :',
                'input' => 'string',
                ));
    }

    /**
     * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array('data_class' => 'BNS\App\ReservationBundle\Form\Model\ReservationEventFormModel',));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::FORM_NAME;
    }
}
