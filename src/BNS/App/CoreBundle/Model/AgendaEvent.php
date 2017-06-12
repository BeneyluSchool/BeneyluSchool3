<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\CoreBundle\Model\om\BaseAgendaEvent;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarNewEventNotification;
use BNS\App\NotificationBundle\Notification\CalendarBundle\CalendarNewEventRecurringNotification;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class AgendaEvent extends BaseAgendaEvent
{
	public static $MIN_HOUR = 8;
	public static $MAX_HOUR = 22;

	private $description;
	private $location;
	private $author;
	private $timeStart;
	private $timeEnd;
	private $recurringType;
	private $recurringCount;
	private $recurringEndDate;

	public static function createDateTimeFromVeventDate(array $date)
	{
		return new \DateTime(implode('-', [$date['year'], $date['month'], $date['day']])
			. 'T'
			. implode(':', [$date['hour'], $date['min'], $date['sec']])
			// without timezone info, dates are saved in the server timezone, so
			// let the DateTime constructor default to it too
			. (isset($date['tz']) ? $date['tz'] : '')
		);
	}

	/**
	 * @return type
	 */
	public function isRecurring()
	{
		return $this->getIsRecurring();
	}

	/**
	 * @return type
	 */
	public function isAllDay()
	{
		return $this->getIsAllDay();
	}

	/**
	 * @param type $str
	 */
	public function setDescription($str)
	{
		$this->description = $str;
	}

	/**
	 * @return type
	 */
	public function getDescription()
	{
		return $this->description;
	}

	/**
	 * @param type $str
	 */
	public function setLocation($str)
	{
		$this->location = $str;
	}

	/**
	 * @return type
	 */
	public function getLocation()
	{
		return $this->location;
	}

	/**
	 * @param type $str
	 */
	public function setAuthor($str)
	{
		$this->author = $str;
	}

	/**
	 * @return type
	 */
	public function getAuthor()
	{
		return $this->author;
	}

	/**
	 * @param type $timeStart
	 */
	public function setTimeStart($timeStart)
	{
		$this->timeStart = $timeStart;
	}

	/**
	 * @return string
	 */
	public function getTimeStart()
	{
		return $this->timeStart;
	}

	/**
	 * @param type $timeEnd
	 */
	public function setTimeEnd($timeEnd)
	{
		$this->timeEnd = $timeEnd;
	}

	/**
	 * @return string
	 */
	public function getTimeEnd()
	{
		return $this->timeEnd;
	}

	/**
	 * @param type $type
	 */
	public function setRecurringType($type)
	{
		$this->recurringType = $type;
	}

	/**
	 * @return type
	 */
	public function getRecurringType()
	{
		return $this->recurringType;
	}

	/**
	 * @param type $count
	 */
	public function setRecurringCount($count)
	{
		$this->recurringCount = $count;
	}

	/**
	 * @return type
	 */
	public function getRecurringCount()
	{
		return $this->recurringCount;
	}

	/**
	 * @param type $endDate
	 */
	public function setRecurringEndDate($endDate)
	{
		$this->recurringEndDate = $endDate;
	}

	/**
	 * @return type
	 */
	public function getRecurringEndDate()
	{
		return $this->recurringEndDate;
	}

	/**
	 * @return string
	 *
	 * @throws RuntimeException
	 */
	public function getRecurrenceString(TranslatorInterface $translator = null)
	{
        if ( null === $translator){
            $translator = BNSAccess::getContainer()->get('translator');
        }

		$recurrenceString = '';

		if (true === $this->isRecurring()) {

            if (null != $this->recurringCount) {

                switch ($this->recurringType) {
                    case 'DAILY':
                        $recurrenceString = $translator->trans('EVENT_DAILY_REPEAT_NUMBER_COUNT', array('%number%' => $this->recurringCount), 'CORE');
                        break;
                    case 'WEEKLY':
                        $recurrenceString = $translator->trans('EVENT_WEEKLY_REPEAT_NUMBER_COUNT', array('%number%' => $this->recurringCount), 'CORE');

                        break;
                    case 'MONTHLY':
                        $recurrenceString = $translator->trans('EVENT_MONTHLY_REPEAT_NUMBER_COUNT', array('%number%' => $this->recurringCount), 'CORE');
                        break;
                    case 'YEARLY':
                        $recurrenceString = $translator->trans('EVENT_YEARLY_REPEAT_NUMBER_COUNT', array('%number%' => $this->recurringCount), 'CORE');
                        break;
                    default:
                        throw new RuntimeException('This point must be never reach!');

                }
            }
            else {
                switch ($this->recurringType) {
                    case 'DAILY':
                        $recurrenceString = $translator->trans('EVENT_DAILY_REPEAT_NUMBER', array('%date%' => BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'long', 'none')), 'CORE');
                        break;
                    case 'WEEKLY':
                        $recurrenceString = $translator->trans('EVENT_WEEKLY_REPEAT_NUMBER', array('%date%' => BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'long', 'none')), 'CORE');
                        break;
                    case 'MONTHLY':
                        $recurrenceString = $translator->trans('EVENT_MONTHLY_REPEAT_NUMBER', array('%date%' => BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'long', 'none')), 'CORE');
                        break;
                    case 'YEARLY':
                        $recurrenceString = $translator->trans('EVENT_YEARLY_REPEAT_NUMBER', array('%date%' => BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'long', 'none')), 'CORE');
                        break;
                    default:
                        throw new RuntimeException('This point must be never reach!');

                }
            }



		}

		return $recurrenceString;
	}

	public function getStart($actualDate = false)
	{
		if ($actualDate) {
			return $this->buildDateTime($this->getDateStart(), $this->getTimeStart());
		}

		$vevent = new \vevent();
		$vevent->parse($this->getIcalendarVevent());
		$dateStart = $vevent->getProperty('dtstart');

		if (is_array($dateStart)) {
			$dt = self::createDateTimeFromVeventDate($dateStart);

			return $dt->format('c');
		}

		return null;
	}

	public function setStart($v = null)
	{
		if ($v) {
			$dt = new \DateTime($v);
			$this->setDateStart($dt);
			$this->setTimeStart($dt->format('H:i:s'));

			return $dt;
		}

		return null;
	}

	public function getEnd($actualDate = false)
	{
		if ($actualDate) {
			return $this->buildDateTime($this->getDateStart(), $this->getTimeStart());
		}

		$vevent = new \vevent();
		$vevent->parse($this->getIcalendarVevent());
		$dateEnd = $vevent->getProperty('dtend');

		if (is_array($dateEnd)) {
			$dt = self::createDateTimeFromVeventDate($dateEnd);

			return $dt->format('c');
		}

		return null;
	}

	public function setEnd($v = null)
	{
		if ($v) {
			$dt = new \DateTime($v);
			$this->setDateEnd($dt);
			$this->setTimeEnd($dt->format('H:i:s'));

			return $dt;
		}

		return null;
	}

	public function getRecurringEnd()
	{
		$dt = $this->getRecurringEndDate();

		return $dt ? $dt->format('c') : null;
	}

	/**
	 * @param \PropelPDO $con
	 *
	 * @return int
	 */
	public function save(\PropelPDO $con = null, $skipNotification = false)
	{
		// Nouvel évènement PAR prof POUR classe
		if ($this->isNew()) {
			$container = BNSAccess::getContainer();
			if ($skipNotification || null == $container) {
				// If $container == null, this method is called by CLI
				return parent::save($con);
			}

			$affectedRows  = parent::save($con);

			$group  = $this->getAgenda()->getGroup();
			$currentUserId = BNSAccess::getUser()->getId();
			$groupUsers    = $container->get('bns.group_manager')->setGroup($group)->getUsersByPermissionUniqueName('CALENDAR_ACCESS', true);

			$finalUsers = array();
			foreach ($groupUsers as $user) {
				if ($user->getId() != $currentUserId) {
					$finalUsers[] = $user;
				}
			}

			if ($this->isRecurring()) {
				$container->get('notification_manager')->send($finalUsers, new CalendarNewEventRecurringNotification($container, $this->getId()));
			}
			else {
				$container->get('notification_manager')->send($finalUsers, new CalendarNewEventNotification($container, $this->getId()));
			}
			return $affectedRows;
		}

		return parent::save($con);
	}

    /**
     * Builds a DateTime from the given date and time.
     *
     * @param ExtendedDateTime $date
     * @param $time
     * @return ExtendedDateTime
     */
    protected function buildDateTime(ExtendedDateTime $date, $time)
    {
        $dt = clone $date;
        $times = explode(':', $time);
        if (3 === count($times)) {
            $dt->setTime($times[0], $times[1], $times[2]);
        }

        return $dt;
    }
}
