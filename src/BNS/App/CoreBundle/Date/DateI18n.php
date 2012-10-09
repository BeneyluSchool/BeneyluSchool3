<?php

namespace BNS\App\CoreBundle\Date;

use Symfony\Component\Locale\Stub\StubIntlDateFormatter;

use BNS\App\CoreBundle\Access\BNSAccess;

class DateI18n 
{
	/**
	 * @param \DateTime|string $date 
	 * @param string $dateFormat
	 * @param string $timeFormat
	 * @param string $pattern Il faut utiliser le pattern de StubIntlDateFormatter, et non les pattern classiques utilisé par Date - PHP
	 * @param string $locale
	 * 
	 * @return string
	 */
	public function process($date, $dateFormat = 'medium', $timeFormat = 'medium', $pattern = null, $locale = null)
	{
		$formatValues = array(
			'none'   => \IntlDateFormatter::NONE,
			'short'  => \IntlDateFormatter::SHORT,
			'medium' => \IntlDateFormatter::MEDIUM,
			'long'   => \IntlDateFormatter::LONG,
			'full'   => \IntlDateFormatter::FULL,
		);
		
		if (!isset($formatValues[$dateFormat]) || !isset($formatValues[$timeFormat])) {
			throw new \InvalidArgumentException('The format is wrong, see \IntlDateFormatter constants !');
		}
		
		$locale = $locale !== null ? $locale : BNSAccess::getLocale();
		
		$formatter = \IntlDateFormatter::create(
			$locale,
			$formatValues[$dateFormat],
			$formatValues[$timeFormat],
			null,
			StubIntlDateFormatter::GREGORIAN,
			$pattern
		);
		
		if (!$date instanceof \DateTime) {
			if (ctype_digit((string) $date)) {
				$date = new \DateTime('@'.$date);
				$date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
			} else {
				$date = new \DateTime($date);
			}
		}
		
		$dateFormatted = $formatter->format($date->getTimestamp());
		//Formatage spécial pour avoir le "h" dans l'heure sans perturber, on surcharge
		if ($locale == "fr" && $timeFormat == "short") {
			$time = substr($dateFormatted, -5);
			$date = substr($dateFormatted, 0, -5);
			$time = str_replace(':', 'h', $time);
			
			return $date . $time;
		}
		
		return $dateFormatted;
	}
}