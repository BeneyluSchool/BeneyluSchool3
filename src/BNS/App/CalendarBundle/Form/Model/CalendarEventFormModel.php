<?php

namespace BNS\App\CalendarBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CoreBundle\Form\Model\IFormModel;
use Symfony\Component\Validator\Context\ExecutionContext;

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
        if (null == $agendaEvent) {
            $this->event = new AgendaEvent();
        }
        else {
            $this->event = $agendaEvent;
            $this->title = $agendaEvent->getTitle();
            $this->agendaId = $agendaEvent->getAgendaId();
            $this->description = $agendaEvent->getDescription();
            $this->location = $agendaEvent->getLocation();
            $this->isAllDay = $agendaEvent->getIsAllDay();
            $this->dateStart = date('Y-m-d', $agendaEvent->getDateStart()->getTimestamp());
            $this->dateEnd = date('Y-m-d', $agendaEvent->getDateEnd()->getTimestamp());

            if (!$this->isAllDay) {
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
                $recurringInfos['UNTIL'] = array('timestamp' => $this->recurringEndDate->getTimestamp());
            }
            else {
                throw new \Exception('Some information about the event\'s recurring is missing!');
            }

            $eventInfos['rrule'] = $recurringInfos;
        }

        if ($this->event->isNew()) {
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
        if (true === $this->isRecurring) {
            if (null == $this->recurringType) {
                $context->buildViolation('ENTER_TYPE_OCCURENCE')
                    ->atPath('recurringType')
                    ->setTranslationDomain('CALENDAR')
                    ->addViolation();
            }

            if (!((null != $this->recurringCount && null == $this->recurringEndDate) || (null == $this->recurringCount && null != $this->recurringEndDate))) {
                $context->buildViolation('ENTER_METHOD_TO_STOP_EVENT')
                    ->atPath('recurringCount')
                    ->setTranslationDomain('CALENDAR')
                    ->addViolation();
            }

            if (null != $this->recurringCount) {
                if (!is_numeric($this->recurringCount) || !($this->recurringCount > 0)) {
                    $context->buildViolation('MUST_BE_INTEGER_POSITIVE')
                        ->atPath('recurringCount')
                        ->setTranslationDomain('CALENDAR')
                        ->addViolation();
                }
            }
            elseif (null != $this->recurringEndDate) {
                $timestampRecurringEndDate = $this->recurringEndDate->getTimestamp();
                if ($this->timestampEnd > $timestampRecurringEndDate) {
                    $context->buildViolation('END_DATE_MUST_BE_UPPER_THAN_BEGIN_DATE')
                        ->atPath('recurringEndDate')
                        ->setTranslationDomain('CALENDAR')
                        ->addViolation();
                }
            }
        }
    }

    public function isValidDateTimeStartAndEnd(ExecutionContext $context)
    {
        if ((false === $this->isAllDay && $this->timestampEnd == $this->timestampStart) ||
			$this->timestampStart != $this->timestampEnd && $this->timestampStart > $this->timestampEnd)
		{
            $context->buildViolation('BEGIN_DATE_BEFORE_END_DATE')
                ->atPath('dateStart')
                ->setTranslationDomain('CALENDAR')
                ->addViolation();
        }

        if (!$this->isAllDay) {
            $start = $this->event->getStart(true);
            $startHour = $start->format('H');
            $startMinutes = $start->format('i');
            $end = $this->event->getEnd(true);
            $endHour = $end->format('H');
            $endMinutes = $end->format('i');

            if ($startHour < AgendaEvent::$MIN_HOUR ||
				$startHour > AgendaEvent::$MAX_HOUR ||
				($startHour == AgendaEvent::$MAX_HOUR && $startMinutes > 0))
			{
                $context->buildViolation('BEGIN_HOURS_BETWEEN_MIN_MAX')
                    ->atPath('timeStart')
                    ->setTranslationDomain('CALENDAR')
                    ->addViolation();
            }

            if ($endHour < AgendaEvent::$MIN_HOUR ||
				$endHour > AgendaEvent::$MAX_HOUR ||
				($endHour == AgendaEvent::$MAX_HOUR && $endMinutes > 0))
            {
                $context->buildViolation('END_HOURS_BETWEEN_MIN_MAX')
                    ->atPath('timeEnd')
                    ->setTranslationDomain('CALENDAR')
                    ->setParameters(array('%min%' => AgendaEvent::$MIN_HOUR, '%max%' => AgendaEvent::$MAX_HOUR))
                    ->addViolation();
            }
        }
    }

	public function getAgendaEvent()
	{
		return $this->event;
	}

    public function getStart()
    {
        return $this->event->getStart();
    }

    public function getEnd()
    {
        return $this->event->getEnd();
    }

    public function setStart($v = null)
    {
        $dt = $this->event->setStart($v);
        $this->timestampStart = $dt ? $dt->getTimestamp() : null;
    }

    public function setEnd($v = null)
    {
        $dt = $this->event->setEnd($v);
        $this->timestampEnd = $dt ? $dt->getTimestamp() : null;
    }

}
