<?php

namespace BNS\App\CoreBundle\Date;


use Symfony\Component\Intl\DateFormatter\IntlDateFormatter;
use Symfony\Component\Translation\TranslatorInterface;

class DateI18n
{
    protected $patterns;
    protected $translator;

    public function __construct(array $patterns, TranslatorInterface $translator)
    {
        $this->patterns = $patterns;
        $this->translator = $translator;
    }


    /**
     * @param \DateTime|string $date
     * @param string $dateFormat
     * @param string $timeFormat
     * @param string $pattern Il faut utiliser le pattern de StubIntlDateFormatter, et non les pattern classiques utilisé par Date - PHP
     * @param string $locale
     *
     * @return string
     */
    public function process($date, $dateFormat = 'medium', $timeFormat = 'short', $pattern = null, $locale = null, \DateTimeZone $timezone = null)
    {
        $formatValues = array(
            'none' => \IntlDateFormatter::NONE,
            'short' => \IntlDateFormatter::SHORT,
            'medium' => \IntlDateFormatter::MEDIUM,
            'long' => \IntlDateFormatter::LONG,
            'full' => \IntlDateFormatter::FULL,
        );

        if (!isset($formatValues[$dateFormat]) || !isset($formatValues[$timeFormat])) {
            throw new \InvalidArgumentException('The format is wrong, see \IntlDateFormatter constants !');
        }

        $locale = $locale !== null ? $locale : $this->translator->getLocale();
        //TODO : use only timezone on all PHP 5.5 plateforms
        $timezone = null !== $timezone ? $timezone->getName() : null;
        if ($pattern) {
            $pattern = $this->getLocalPattern($pattern, $locale);
        }

        $formatter = \IntlDateFormatter::create(
            $locale,
            $formatValues[$dateFormat],
            $formatValues[$timeFormat],
            $timezone,
            IntlDateFormatter::GREGORIAN,
            $pattern
        );

        if (!$date instanceof \DateTime) {
            if (ctype_digit((string)$date)) {
                $date = new \DateTime('@' . $date);
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

    protected function getLocalPattern($pattern, $locale)
    {
        $localeShort = explode('_', $locale)[0];

        foreach ($this->patterns as $element) {
            if ($element['pattern'] == $pattern) {
                if (isset($element['locales'][$locale])) {
                    return ($element['locales'][$locale]);
                } else if (isset($element['locales'][$localeShort])) {
                    return ($element['locales'][$localeShort]);
                } else {
                    return $pattern;
                }
            }
        }

        return $pattern;
    }
}
