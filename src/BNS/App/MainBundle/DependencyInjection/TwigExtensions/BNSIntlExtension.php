<?php

namespace BNS\App\MainBundle\DependencyInjection\TwigExtensions;

use Symfony\Component\DependencyInjection\ContainerInterface;

class BNSIntlExtension  extends \Twig_Extension
{
	private $container;
	
    public function __construct(ContainerInterface $container)
    {
        if (!class_exists('IntlDateFormatter')) {
            throw new RuntimeException('The intl extension is needed to use intl-based filters.');
        }
		
		$this->container = $container;
    }

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        return array(
            'date_bns' => new \Twig_Filter_Method($this,'twig_localized_bns_date_filter'),
			'year_month_bns' => new \Twig_Filter_Method($this,'twig_localized_year_month_bns_filter')
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'bns-intl';
    }
	
	function twig_localized_year_month_bns_filter($date, $locale = null)
	{
		$dateFormat = 'full'; 
		$timeFormat = 'none';
		
		$formatValues = array(
			'none'   => \IntlDateFormatter::NONE,
			'full'   => \IntlDateFormatter::FULL,
		);

		$locale = $locale !== null ? $locale : \Locale::getDefault();

		$formatter = \IntlDateFormatter::create(
			$locale,
			$formatValues[$dateFormat],
			$formatValues[$timeFormat]
		);
		
		if (!$date instanceof \DateTime) {
			if (ctype_digit((string) $date)) {
				$date = new \DateTime('@'.$date);
				$date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
			} else {
				$date = new \DateTime($date);
			}
		}
		
		$date_formatted = $formatter->format($date->getTimestamp());;
		
		//Faire un explode pour récupérer que le mois et l'année
		$explodedDate = explode( " " , $date_formatted);
		
		
		return ucfirst($explodedDate[2])." ".$explodedDate[3];
	}
	
	function twig_localized_bns_date_filter($date, $dateFormat = 'medium', $timeFormat = 'medium', $locale = null)
	{
		return $this->container->get('date_i18n')->process($date, $dateFormat, $timeFormat, $locale);
	}
	
}