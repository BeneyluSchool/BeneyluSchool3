<?php

namespace BNS\App\CoreBundle\Model;

use Symfony\Component\Process\Exception\RuntimeException;

use BNS\App\CoreBundle\Model\om\BaseAgendaEvent;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'agenda_event' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class AgendaEvent extends BaseAgendaEvent {
	private $description;
	private $location;
	private $author;
	private $timeStart;
	private $timeEnd;
	private $recurringType;
	private $recurringCount;
	private $recurringEndDate;
        
	public static $MIN_HOUR = 8;
	public static $MAX_HOUR = 20;
	
	public function isRecurring()
	{
		return $this->getIsRecurring();
	}
	
	public function isAllDay()
	{
		return $this->getIsAllDay();
	}
	
	public function setDescription($str)
	{
		$this->description = $str;
	}
	
	public function getDescription()
	{
		return $this->description;
	}
	
	public function setLocation($str)
	{
		$this->location = $str;
	}
	
	public function getLocation()
	{
		return $this->location;
	}
	
	public function setAuthor($str)
	{
		$this->author = $str;
	}
	
	public function getAuthor()
	{
		return $this->author;
	}
	
	public function setTimeStart($timeStart)
	{
		$this->timeStart = $timeStart;
	}
	
	public function getTimeStart()
	{
		return $this->timeStart;
	}
	
	public function setTimeEnd($timeEnd)
	{
		$this->timeEnd = $timeEnd;
	}
	
	public function getTimeEnd()
	{
		return $this->timeEnd;
	}
	
	public function setRecurringType($type)
	{
		$this->recurringType = $type;
	}
	
	public function getRecurringType()
	{
		return $this->recurringType;
	}
	
	public function setRecurringCount($count)
	{
		$this->recurringCount = $count;
	}
	
	public function getRecurringCount()
	{
		return $this->recurringCount;
	}
	
	public function setRecurringEndDate($endDate)
	{
		$this->recurringEndDate = $endDate;
	}
	
	public function getRecurringEndDate()
	{
		return $this->recurringEndDate;
	}
	
	public function getRecurrenceString()
	{
		$recurrenceString = '';
		
		if (true === $this->isRecurring()) {
			$recurrenceString = 'Cet événement a lieu ';
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
				$recurrenceString .= ', répété '. $this->recurringCount .' fois.';
			}
			else {
				$recurrenceString .= ' jusqu\'au '. BNSAccess::getContainer()->get('date_i18n')->process($this->recurringEndDate, 'full', 'none') . '.';
			}
		}
		
		return $recurrenceString;
	}
	
} // AgendaEvent
