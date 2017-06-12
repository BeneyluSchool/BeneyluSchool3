<?php

namespace BNS\CommonBundle\Locale;

use Negotiation\LanguageNegotiator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class LocaleManager
 *
 * @package BNS\App\CoreBundle\Locale
 */
class LocaleManager
{

    const USER_DEFINED_LOCALE_FLAG = 'user_defined_locale';

    /**
     * @var array
     */
    public $availableLanguages;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /** @var  string locale */
    private $defaultLocale;

    public function __construct($availableLanguages, TranslatorInterface $translator, $defaultLocale)
    {
        $this->negotiableLanguages = array_map( function($item) {
            return str_replace('_', '-', $item);
        }, $availableLanguages);

        $this->availableLanguages = $availableLanguages;
        $this->translator = $translator;
        $this->defaultLocale = in_array($defaultLocale, $this->availableLanguages) ? $defaultLocale : reset($this->availableLanguages);
    }

    public function getNiceAvailableLanguages()
    {
        $languages = [];
        foreach ($this->availableLanguages as $key) {
            $lang = $key;
            $region = null;
            // TODO use LocalManager
            if (strpos($lang, '_')) {
                $temp = explode('_', $lang);
                $lang = $temp[0];
                $region = $temp[1];
            }

            if ('es' === $key) {
                $languages[$key] = 'Español (Castellano)';
            } else if ('es_AR' === $key) {
                $languages[$key] = 'Español Rioplatense';
            } else {
                $languages[$key] = ucfirst(Intl::getLanguageBundle()->getLanguageName($lang, $region , $lang));
            }
        }

        return $languages;
    }

    /**
     * Gets the default locale
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    public function localeOrDefault($locale)
    {
        if (isset($locale, $this->availableLanguages)) {
            return $locale;
        }

        return $this->getDefaultLocale();
    }

    /**
     * Determines the best locale for the given request, among available languages.
     * Also updates request and session with the guessed locale.
     *
     * @param Request $request
     */
    public function setBestLocale(Request $request)
    {
        $session = $request->getSession();

        $lang = $this->getBestLocale($request->server->get('HTTP_ACCEPT_LANGUAGE'));

        if (null === $session->get('_locale')) {
            $this->translator->setLocale($lang);
        }

        $session->set('_locale', $lang);
        $request->setLocale($lang);
    }

    public function getBestLocale($negotiatedLocal)
    {
        $negotiator = new LanguageNegotiator();
        // fix potential issue fr_FR vs fr-fr
        $negotiatedLocal = str_replace('_', '-', $negotiatedLocal);

        $guess = $negotiator->getBest($negotiatedLocal, $this->negotiableLanguages);
        if ($guess) {
            $lang = $guess->getValue();
        } else {
            $lang = $this->getDefaultLocale();
        }

        $lang = str_replace('-', '_', $lang);

        return $lang;
    }

    public function hasUserDefinedLocale(SessionInterface $session)
    {
        return $session->has(self::USER_DEFINED_LOCALE_FLAG);
    }

    public function flagUserDefinedLocale(SessionInterface $session)
    {
        $session->set(self::USER_DEFINED_LOCALE_FLAG, true);
    }

    public function unflagUserDefinedLocale(SessionInterface $session)
    {
        return $session->remove(self::USER_DEFINED_LOCALE_FLAG);
    }

}
