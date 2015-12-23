<?php

namespace BNS\App\ReservationBundle\Model;

use BNS\App\ReservationBundle\Model\om\BaseReservationEvent;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Component\Process\Exception\RuntimeException;
use BNS\App\CoreBundle\Utils\String;

class ReservationEvent extends BaseReservationEvent
{
    public static $MIN_HOUR = 8;
    public static $MAX_HOUR = 20;

    private $description;
    private $location;
    private $author;
    private $timeStart;
    private $timeEnd;
    private $recurringType;
    private $recurringCount;
    private $recurringEndDate;

    public function getColorClass()
    {
        if ($this->getReservationItem()) {
            return $this->getReservationItem()->getColorClass();
        }

        return 'cal-blue';
    }

    /**
     * @return type
     */
    public function isRecurring()
    {
        return $this->getIsRecurring();
    }

    /**
     * @return string
     */
    public function getShortDescription()
    {
        return String::substrws($this->getDescription());
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
     * @return type
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
     * @return type
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
    public function getRecurrenceString()
    {
        $recurrenceString = '';

        if (true === $this->isRecurring()) {
            $recurrenceString = 'Cette réservation a lieu ';

            switch ($this->recurringType) {
                case 'DAILY':
                    $recurrenceString .= 'tous les jours';
                    break;
                case 'WEEKLY':
                    $recurrenceString .= 'toutes les semaines';
                    break;
                case 'MONTHLY':
                    $recurrenceString .= 'tous les mois';
                    break;
                case 'YEARLY':
                    $recurrenceString .= 'tous les ans';
                    break;
                default:
                    throw new RuntimeException('This point must be never reach!');

            }

            if (null != $this->recurringCount) {
                $recurrenceString .= ', répété ' . $this->recurringCount . ' fois.';
            }
            else {
                $recurrenceString .= " jusqu'au " . BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'full', 'none') . '.';
            }
        }

        return $recurrenceString;
    }

    public function getTitle()
    {
        return $this->getReservationItem()?$this->getReservationItem()->getTitle() : parent::getTitle();
    }

    /**
     * @param \PropelPDO $con
     *
     * @return int
     */
    public function save(\PropelPDO $con = null)
    {
        // Nouvel évènement PAR prof POUR classe
        if ($this->isNew()) {
            $container = BNSAccess::getContainer();
            if (null == $container) {
                // If $container == null, this method is called by CLI
                return parent::save($con);
            }

            $affectedRows  = parent::save($con);

            $currentGroup  = $container->get('bns.right_manager')->getCurrentGroup();
            $currentUserId = BNSAccess::getUser()->getId();
            $groupUsers    = $container->get('bns.group_manager')->setGroup($currentGroup)->getUsersByPermissionUniqueName('RESERVATION_ACCESS', true);

            $finalUsers = array();
            foreach ($groupUsers as $user) {
                if ($user->getId() != $currentUserId) {
                    $finalUsers[] = $user;
                }
            }

//             if ($this->isRecurring()) {
//                 $container->get('notification_manager')->send($finalUsers, new CalendarNewEventRecurringNotification($container, $this->getId()));
//             }
//             else {
//                 $container->get('notification_manager')->send($finalUsers, new CalendarNewEventNotification($container, $this->getId()));
//             }
            return $affectedRows;
        }

        return parent::save($con);
    }
}
