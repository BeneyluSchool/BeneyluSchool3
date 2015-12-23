<?php

namespace BNS\App\ReservationBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\ReservationBundle\Model\ReservationEvent;
use BNS\App\CoreBundle\Form\Model\IFormModel;

use Symfony\Component\Validator\ExecutionContext;

class ReservationEventFormModel implements IFormModel
{
    /**
        * @var ReservationEvent correspond à l'objet ReservationEvent que l'on souhaite modifier
        */
    public $event;

    /**
        * @var String
        */
    public $title;

    /**
        * @var Integer entier représentant l'id de l'agenda auquel l'événement va être associé
        */
    public $reservationId;

    public $reservationItem;

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

    public function __construct(ReservationEvent $reservationEvent = null)
    {
        if (null == $reservationEvent) {
            $this->event = new ReservationEvent();
        }
        else {
            $this->event = $reservationEvent;
            $this->title = $reservationEvent->getTitle();
            $this->reservationId = $reservationEvent->getReservationId();
            $this->reservationItem = $reservationEvent->getReservationItem();
            $this->description = $reservationEvent->getDescription();
            $this->location = $reservationEvent->getLocation();
            $this->isAllDay = $reservationEvent->getIsAllDay();
            $this->dateStart = date('Y-m-d', $reservationEvent->getDateStart()->getTimestamp());
            $this->dateEnd = date('Y-m-d', $reservationEvent->getDateEnd()->getTimestamp());

            if (!$this->isAllDay) {
                $this->timeStart = $reservationEvent->getTimeStart();
                $this->timeEnd = $reservationEvent->getTimeEnd();
            }

            $this->isRecurring = $reservationEvent->getIsRecurring();
            if ($this->isRecurring) {
                $this->recurringType = $reservationEvent->getRecurringType();
                if ('' != $reservationEvent->getRecurringCount()) {
                        $this->recurringCount = $reservationEvent->getRecurringCount();
                }
                else {
                        $this->recurringEndDate = $reservationEvent->getRecurringEndDate();
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
            'reservationItem' => $this->reservationItem,
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

        if ($this->event->isNew()) {
            $eventInfos['organizer'] = BNSAccess::getUser()->getFullName();

            $this->event = BNSAccess::getContainer()->get('bns.reservation_manager')->createEvent($this->reservationId, $eventInfos);
        }
        else {
            $eventInfos['organizer'] = $this->event->getAuthor();

            $this->event = BNSAccess::getContainer()->get('bns.reservation_manager')->editEvent($this->event, $eventInfos);
        }
    }

    public function isValidRecurringOptions($context)
    {
        $this->timestampStart = strtotime($this->dateStart. (false === $this->isAllDay? ' '.$this->timeStart : ''));
        $this->timestampEnd = strtotime($this->dateEnd. (false === $this->isAllDay? ' '.$this->timeEnd : ''));

        if (true === $this->isRecurring) {
            if (null == $this->recurringType) {
                $context->addViolationAt('recurringType', 'Vous devez renseigner un type d\'occurrence.');
            }

            if (!((null != $this->recurringCount && null == $this->recurringEndDate) || (null == $this->recurringCount && null != $this->recurringEndDate))) {
                $context->addViolationAt('recurringCount', 'Vous devez choisir une unique façon d\'arrêter votre événement. Soit en indiquant une date de fin, soit après un nombre de répétitions.');
            }

            if (null != $this->recurringCount) {
                if (!is_numeric($this->recurringCount) || !($this->recurringCount > 0)) {
                    $context->addViolationAt('recurringCount', 'Le champ du nombre de répétition doit être un chiffre ou un nombre supérieur à 0.');
                }
            }
            elseif (null != $this->recurringEndDate) {
                $timestampRecurringEndDate = strtotime($this->recurringEndDate);
                if ($this->timestampEnd > $timestampRecurringEndDate) {
                    $context->addViolationAt('recurringEndDate', 'La date de fin de récurrence de l\'événement doit être supérieur à la date de fin de l\'événement.');
                }
            }
        }
    }

    public function isValidDateTimeStartAndEnd($context)
    {
        if ((false === $this->isAllDay && $this->timestampEnd == $this->timestampStart) ||
			$this->timestampStart != $this->timestampEnd && $this->timestampStart > $this->timestampEnd)
		{
            $context->addViolationAt('dateStart', 'Le début de l\'événement doit avoir une date et un horaire antérieurs à la date de fin.');
        }

        if (!$this->isAllDay) {
            $startHour = date('H', $this->timestampStart);
            $startMinutes = date('i', $this->timestampStart);
            $endHour = date('H', $this->timestampEnd);
            $endMinutes = date('i', $this->timestampEnd);

            if ($startHour < ReservationEvent::$MIN_HOUR ||
				$startHour > ReservationEvent::$MAX_HOUR ||
				($startHour == ReservationEvent::$MAX_HOUR && $startMinutes > 0))
			{
                $context->addViolationAt('timeStart', 'L\'heure de début de l\'événement doit être comprise entre '.ReservationEvent::$MIN_HOUR.'h et '.ReservationEvent::$MAX_HOUR.'h.');
            }

            if ($endHour < ReservationEvent::$MIN_HOUR ||
				$endHour > ReservationEvent::$MAX_HOUR ||
				($endHour == ReservationEvent::$MAX_HOUR && $endMinutes > 0))
            {
                $context->addViolationAt('timeEnd', 'L\'heure de fin de l\'événement doit être comprise entre '.ReservationEvent::$MIN_HOUR.'h et '.ReservationEvent::$MAX_HOUR.'h.');
            }
        }
    }

	public function getReservationEvent()
	{
		return $this->event;
	}
}