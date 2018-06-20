<?php

namespace BNS\App\CalendarBundle\Form\Type;

use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\AgendaObjectQuery;
use BNS\App\CoreBundle\Model\AgendaSubjectQuery;
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
		$builder->add('title', 'text', array('required' => false));

		// Agenda concerné par l'événement
		foreach ($this->myAgendas as $agenda) {
		    /** @var Agenda $agenda */
			$agendas[$agenda->getId()] = $agenda->getTitle();
			$groupIds[] = $agenda->getGroupId();
		}

		$builder->add('agendaId', 'choice', array(
			'choices' 	=> $agendas,
			'expanded'	=> true,
			'required' 	=> false,
		));

		$subjectIds =  AgendaSubjectQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();
		foreach ($subjectIds as $subjectId) {
		    $subjectChoice[$subjectId] = $subjectId;
        }

        $builder->add('subjectId', 'choice', array(
            'choices' 	=> $subjectChoice,
            'expanded'	=> true,
            'required' 	=> false,
        ));
        $objectIds =  AgendaObjectQuery::create()->filterByGroupId($groupIds)->select('id')->find()->toArray();

        foreach ($objectIds as $objectId) {
            $objectChoice[$objectId] = $objectId;
        }

        $builder->add('objectId', 'choice', array(
            'choices' 	=> $objectChoice,
            'expanded'	=> true,
            'required' 	=> false,
        ));

        $builder->add('timeStart', 'text', array(
			'required'	=> false,
		));

		$builder->add('timeEnd', 'text', array(
			'required'	=> false,
		));

		$builder->add('start', 'text');
		$builder->add('end', 'text');

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
			'DAILY' 	=> 'LABEL_DAILY',
			'WEEKLY' 	=> 'LABEL_WEEKLY',
			'MONTHLY' 	=> 'LABEL_MONTHLY',
			'YEARLY' 	=> 'LABEL_YEARLY',
		);
		// Récurrence Type
		$builder->add('recurringType', 'choice', array(
			'choices' 		=> $choices,
			'required' 		=> false,
			'label'			=> 'LABEL_TYPE_OCCURRENCE',
			'empty_value'	=> 'LABEL_EMPTY_OCCURRENCE',
			'empty_data'  	=> null
		));

		$builder->add('type', 'choice', array(
		    'choices' => array('PUNCTUAL' => 0, 'DISCIPLINE' => 1, 'RESERVATION' => 2),
            'required' => true,
            'empty_data' => 'PUNCTUAL'
        ));

		// Nombre récurrence
		$builder->add('recurringCount', 'text', array(
			'required' 	=> false,
			'label'		=> 'LABEL_DURING',
		));

		// ou une date de fin
		$builder->add('recurringEndDate', 'datetime', array(
			'required'	=> false,
			'widget' => 'single_text',
			'label'     => 'LABEL_END_DATE',
			'input'	=> 'datetime',
		));

		$builder->add('resource-joined', 'hidden', array(
			'required' => false,
			'mapped' => false,
		));
	}

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\CalendarBundle\Form\Model\CalendarEventFormModel',
            'translation_domain' => 'CALENDAR'
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
