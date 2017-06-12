<?php

namespace BNS\App\CoreBundle\Date;

use DateTime;
use JMS\TranslationBundle\Annotation\Ignore;

use BNS\App\CoreBundle\Access\BNSAccess;

class ExtendedDateTime extends DateTime
{
	private $day;
	private $month;

	/**
	 * @return string Date to format d/m/Y, example : 31/05/98
	 */
	public function getDate()
	{
		return date('d/m/Y', $this->getTimestamp());
	}

	/**
	 * @return string
	 */
	public function getHours()
	{
		return date('H', $this->getTimestamp());
	}

	/**
	 * @return string
	 */
	public function getMinutes()
	{
		return date('i', $this->getTimestamp());
	}

	/**
	 * @return string
	 */
	public function getSeconds()
	{
		return date('s', $this->getTimestamp());
	}

	/**
	 * @return string Full date
	 */
	public function __toString()
	{
		return date('d/m/Y H:i:s', $this->getTimestamp());
	}

	/**
	 * @return string
	 */
	public function getMonth()
	{
		if (!isset($this->month)) {
			$this->process();
		}

		return $this->month;
	}

	/**
	 * @return string
	 */
	public function getDay()
	{
		if (!isset($this->day)) {
			$this->process();
		}

		return $this->day;
	}

	/**
	 * @return int
	 */
	public function getYear()
	{
		return date('Y', $this->getTimestamp());
	}

	/**
	 * @return string
	 */
	public function getTime()
	{
		return date('H:i:s', $this->getTimestamp());
	}

	/**
	 *
	 */
	private function process()
	{
		$translator		= BNSAccess::getContainer()->get('translator');
        /** @Ignore */
		$this->month	= $translator->trans(date('F', $this->getTimestamp()));
        /** @Ignore */
		$this->day		= $translator->trans(date('l', $this->getTimestamp()));
	}
}
