<?php

namespace BNS\App\CalendarBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CoreBundle\Form\Model\IFormModel;

use Symfony\Component\Validator\ExecutionContext;

class CalendarEventFormModel implements IFormModel
{
    /**
        * @var AgendaEvent correspond à l'objet AgendaEvent que l'on souhaite modifier
        */
    public $event;

    /**
        * @var String
        */
    public $title;

    /**
        * @var Integer entier représentant l'id de l'agenda auquel l'événement va être associé
        */
    public $agendaId;

    /**
        * @var String
        */
    public $description;

    /**
        * @var String
        */
    public $location;

    /**
        * @var DateTime string - format : 'Y-m-d'
        */
    public $dateStart;

    /**
        * @var DateTime string - format : 'Y-m-d'
        */
    public $dateEnd;

    /**
        * @var unknown_type
        */
    public $timeStart;

    /**
        * @var unknown_type
        */
    public $timeEnd;

    /**
        * @var boolean
        */
    public $isAllDay;

    /**
        * @var boolean
        */
    public $isRecurring;

    /**
        * @var String
        */
    public $recurringType;

    /**
        * @var Integer
        */
    public $recurringCount;

    /**
        * @var Date
        */
    public $recurringEndDate;

    private $timestampStart;

    private $timestampEnd;

    public function __construct(AgendaEvent $agendaEvent = null)
    {
        if (null == $agendaEvent)
        {
            $agendaEvent = new AgendaEvent();
        }
        else
        {
            $this->event = $agendaEvent;
            $this->title = $agendaEvent->getTitle();
            $this->agendaId = $agendaEvent->getAgendaId();
            $this->description = $agendaEvent->getDescription();
            $this->location = $agendaEvent->getLocation();
            $this->isAllDay = $agendaEvent->getIsAllDay();
            $this->dateStart = date('Y-m-d', $agendaEvent->getDateStart()->getTimestamp());
            $this->dateEnd = date('Y-m-d', $agendaEvent->getDateEnd()->getTimestamp());
            if (!$this->isAllDay)
            {
                $this->timeStart = $agendaEvent->getTimeStart();
                $this->timeEnd = $agendaEvent->getTimeEnd();
            }

            $this->isRecurring = $agendaEvent->getIsRecurring();
            if ($this->isRecurring) {
                $this->recurringType = $agendaEvent->getRecurringType();
                if ('' != $agendaEvent->getRecurringCount()) {
                        $this->recurringCount = $agendaEvent->getRecurringCount();
                }
                else {
                        $this->recurringEndDate = $agendaEvent->getRecurringEndDate();
                }
            }
        }
    }

    public function save()
    {
        $eventInfos = array(
            'summary'       => $this->title,
            'description'   => str_replace(array('\n', CHR(13), CHR(10)), '', $this->description),
            'location'      => $this->location,
            'dtstart'       => $this->timestampStart,
            'dtend'         => $this->timestampEnd,
            'allday'        => $this->isAllDay,
            'rrule'         => '',
        );
        
        if (true === $this->isRecurring) {
            $recurringInfos = array();
            $recurringInfos['FREQ'] = $this->recurringType;
            if (null != $this->recurringCount) {
                $recurringInfos['COUNT'] = $this->recurringCount;
            }
            elseif (null != $this->recurringEndDate) {
                $recurringInfos['UNTIL'] = array('timestamp' => strtotime($this->recurringEndDate));
            }
            else {
                throw new \Exception('Some information about the event\'s recurring is missing!');
            }

            $eventInfos['rrule'] = $recurringInfos;
        }

        if (null == $this->event) {
            $eventInfos['organizer'] = BNSAccess::getUser()->getFullName();

            $this->event = BNSAccess::getContainer()->get('bns.calendar_manager')->createEvent($this->agendaId, $eventInfos);
        }
        else {
            $eventInfos['organizer'] = $this->event->getAuthor();
            $this->event->setAgendaId($this->agendaId);

            $this->event = BNSAccess::getContainer()->get('bns.calendar_manager')->editEvent($this->event, $eventInfos);
        }
    }

    public function isValidRecurringOptions(ExecutionContext $context)
    {
        $this->timestampStart = strtotime($this->dateStart. (false === $this->isAllDay? ' '.$this->timeStart : ''));
        $this->timestampEnd = strtotime($this->dateEnd. (false === $this->isAllDay? ' '.$this->timeEnd : ''));

        if (true === $this->isRecurring) {
            if (null == $this->recurringType) {
                $context->addViolationAtSubPath('recurringType', 'Vous devez renseigner un type d\'occurrence.');
            }

            if (!((null != $this->recurringCount && null == $this->recurringEndDate) || (null == $this->recurringCount && null != $this->recurringEndDate))) {
                $context->addViolationAtSubPath('recurringCount', 'Vous devez choisir une unique façon d\'arrêter votre événement. Soit en indiquant une date de fin, soit après un nombre de répétitions.');
            }

            if (null != $this->recurringCount) {
                if (!is_numeric($this->recurringCount) || !($this->recurringCount > 0)) {
                    $context->addViolationAtSubPath('recurringCount', 'Le champ du nombre de répétition doit être un chiffre ou un nombre supérieur à 0.');
                }
            }
            elseif (null != $this->recurringEndDate) {
                $timestampRecurringEndDate = strtotime($this->recurringEndDate);
                if ($this->timestampEnd > $timestampRecurringEndDate) {
                    $context->addViolationAtSubPath('recurringEndDate', 'La date de fin de récurrence de l\'événement doit être supérieur à la date de fin de l\'événement.');
                }
            }
        }
    }

    public function isValidDateTimeStartAndEnd(ExecutionContext $context)
    {
        if ($this->timestampStart != $this->timestampEnd && ($this->timestampStart > $this->timestampEnd ||
			(false === $this->isAllDay && $this->timestampEnd == $this->timestampStart)))
		{
            $context->addViolationAtSubPath('dateStart', 'Le début de l\'événement doit avoir une date et un horaire ultérieurs à la date de fin.');
        }
        
        if (!$this->isAllDay) {
            $startHour = date('H', $this->timestampStart);
            $startMinutes = date('i', $this->timestampStart);
            $endHour = date('H', $this->timestampEnd);
            $endMinutes = date('i', $this->timestampEnd);

            if ($startHour < AgendaEvent::$MIN_HOUR ||
				$startHour > AgendaEvent::$MAX_HOUR ||
				($startHour == AgendaEvent::$MAX_HOUR && $startMinutes > 0))
			{
                $context->addViolationAtSubPath('timeStart', 'L\'heure de début de l\'événement doit être comprise entre '.AgendaEvent::$MIN_HOUR.'h et '.AgendaEvent::$MAX_HOUR.'h.');
            }

            if ($endHour < AgendaEvent::$MIN_HOUR ||
				$endHour > AgendaEvent::$MAX_HOUR ||
				($endHour == AgendaEvent::$MAX_HOUR && $endMinutes > 0))
            {
                $context->addViolationAtSubPath('timeEnd', 'L\'heure de fin de l\'événement doit être comprise entre '.AgendaEvent::$MIN_HOUR.'h et '.AgendaEvent::$MAX_HOUR.'h.');
            }
        }
    }
	
	public function getAgendaEvent()
	{
		return $this->event;
	}
}