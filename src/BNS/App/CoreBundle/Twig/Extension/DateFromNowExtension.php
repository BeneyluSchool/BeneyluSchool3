<?php

namespace BNS\App\CoreBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

/**
 * @author Eric Chau <eric.chau@pixel-cookers.com>
 * 
 *  Date : 4 juillet 2012
 */
class DateFromNowExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Initialize autosave helper
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'date_from_now' => new Twig_Function_Method($this, 'dateFromNow', array('is_safe' => array('html')))
        );
    }

    /**
	 * DateFromNow initializations
	 * 
	 * @return string 
	 */
    public function dateFromNow($timestamp, $isFirstLetterCap = false, $usePrefix = false, $dateFormat = 'medium', $timeFormat = 'short', $isFullDate = false)
    {
		$dateTime = $timestamp;
		if ($timestamp instanceof \DateTime) {
			$timestamp = $timestamp->getTimestamp();
		}
		
		$diffTimestamp = time() - $timestamp;
		$minuteToSecond	= 60;
		$hourToSecond	= 60 * $minuteToSecond;
		$dayToSecond	= 24 * $hourToSecond;
		
		$translator = $this->container->get('translator');
		$dateI18n = $this->container->get('date_i18n');
		$dateFromNowString = $translator->trans('twig-extension.date-from-now.since');
		$fullDate = $dateI18n->process($dateTime, $dateFormat, 'none') . ' ' . $translator->trans('twig-extension.date-from-now.at') . ' ' . $dateI18n->process($dateTime, 'none', $timeFormat);;
		$count = 0;
		
		// Date future, full date retournée quoi qu'il arrive
		if (0 > $diffTimestamp) {
			if ($usePrefix) {
				$dateFromNowString = $translator->trans('twig-extension.date-from-now.on') . ' ' . $fullDate;
				if (!$isFirstLetterCap) {
					$dateFromNowString = mb_strtolower($dateFromNowString, 'UTF-8');
				}
			}
			else {
				$dateFromNowString = $fullDate;
			}
			
			return $this->container->get('templating')->render('BNSAppCoreBundle:DateFromNowExtension:render.html.twig', array(
				'full_date'		=> $fullDate,
				'date_string'	=> $dateFromNowString
			));
		}
				
		// Date il y a quelques secondes
		if ($diffTimestamp < $minuteToSecond) {
			$dateFromNowString .= ' ' . $translator->trans('twig-extension.date-from-now.seconde');
		}
		// Date il y a quelques minutes
		elseif ($diffTimestamp > $minuteToSecond && $diffTimestamp < $hourToSecond) {
			$count = (int) ($diffTimestamp / $minuteToSecond);
			$dateFromNowString .= ' '. $translator->transChoice('twig-extension.date-from-now.minute', $count, array('%count%' => $count));
		}
		// Date il y a quelques heures
		elseif ($diffTimestamp > $hourToSecond && $diffTimestamp < $hourToSecond * 13) {
			$count = (int) ($diffTimestamp / $hourToSecond);
			$dateFromNowString .= ' '. $translator->transChoice('twig-extension.date-from-now.hour', $count, array('%count%' => $count));
		}
		elseif ($diffTimestamp > $hourToSecond * 13 && $diffTimestamp < $dayToSecond) {
			$prefix = '';
			if (date('d/m/Y') == date('d/m/Y', $timestamp)) {
				$prefix .= $translator->trans('twig-extension.date-from-now.today');
			}
			else {
				$prefix .= $translator->trans('twig-extension.date-from-now.yesterday');
			}
			
			$dateFromNowString = $prefix . ' ' . $dateI18n->process($dateTime, 'none', 'short');
		}
		else {
			if ($usePrefix) {
				$dateFromNowString = $translator->trans('twig-extension.date-from-now.on') . ' ' . $fullDate;
			}
			else {
				$dateFromNowString = $fullDate;
			}
		}
		
		if (!$isFirstLetterCap) {
			$dateFromNowString = mb_strtolower($dateFromNowString, 'UTF-8');
		}
		
		return $this->container->get('templating')->render('BNSAppCoreBundle:DateFromNowExtension:render.html.twig', array(
			'full_date'		=> $fullDate,
			'date_string'	=> $dateFromNowString
		));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'date_from_now';
    }
}