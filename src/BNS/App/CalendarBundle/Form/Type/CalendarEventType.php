<?php

namespace BNS\App\CalendarBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class CalendarEventType extends AbstractType
{
	const FORM_NAME = 'calendar_event_form';
	
	private $myAgendas;
	
	private $userLocale;
	
	public function __construct($myAgendas, $locale)
	{
		$this->myAgendas = $myAgendas;
		$this->userLocale = $locale;
	}
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{	
		// Titre
		$builder->add('title', 'text');
		
		// Agenda concerné par l'événement
		foreach ($this->myAgendas as $agenda)
		{
                    $agendas[$agenda->getId()] = $agenda->getTitle();
		}
                
		$builder->add('agendaId', 'choice', array(
                    'choices' 	=> $agendas,
                    'required' 	=> true,
		));
		
		
		$date_format = ($this->userLocale == 'fr' || $this->userLocale == 'fr' ? 'dd/MM/yyyy' : 'MM-dd-yyyy');
		// Date et Horaire de début
		$builder->add('dateStart', 'date', array(
                    'input'		=> 'string',
                    'widget'	=> 'single_text',
                    'format'	=> $date_format,
                    'required' 	=> true,
		));
		
		$builder->add('timeStart', 'time', array(
                    'input'	=> 'string',
                    'widget'	=> 'single_text',
                    'required'	=> false,
		));
		
		// Date et Horaire de fin
		$builder->add('dateEnd', 'date', array(
                    'input'			=> 'string',
                    'widget'	=> 'single_text',
                    'format'	=> $date_format,
                    'required' 	=> true,
		));
		
		$builder->add('timeEnd', 'time', array(
                    'input'	=> 'string',
                    'widget'	=> 'single_text',
                    'required'	=> false,
		));	
		
		// Toute la journée ?
		$builder->add('isAllDay', 'checkbox', array(
                    'required'	=> false,
		));
		
		// Description (Facultatif)
		$builder->add('description', 'textarea', array(
                    'required' => false,
		)); 
		
		// Lieu (Facultatif)
		$builder->add('location', 'text', array(
                    'required' => false,
		)); 
		
		// Récurrence ?
		$builder->add('isRecurring', 'checkbox', array(
                    'required'	=> false,
		));
		
		$choices = array(
                    'DAILY' 	=> 'Tous les jours',
                    'WEEKLY' 	=> 'Toutes les semaines', 
                    'MONTHLY' 	=> 'Tous les mois', 
                    'YEARLY' 	=> 'Tous les ans',
		);
		// Récurrence Type
		$builder->add('recurringType', 'choice', array(
                    'choices' 		=> $choices,
                    'required' 		=> false,
                    'label'			=> 'Quelle type d\'ocurrence',
                    'empty_value'	=> 'Type d\'occurrence...',
                    'empty_data'  	=> null
		));
		
		// Nombre récurrence 
		$builder->add('recurringCount', 'text', array(
                    'required' 	=> false,
                    'label'		=> 'Durant :',
		));
		
		// ou une date de fin
		$builder->add('recurringEndDate', 'date', array(
                    'widget'	=> 'single_text',
                    'format'	=> $date_format,
                    'required'	=> false,
                    'label'     => 'Ou une date de fin :',
                    'input'	=> 'string',
		));
	}
	
	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CalendarBundle\Form\Model\CalendarEventFormModel',
        ));
    }
	
	/**
	 * @return string 
	 */
	public function getName()
	{
		return self::FORM_NAME;
	}
}