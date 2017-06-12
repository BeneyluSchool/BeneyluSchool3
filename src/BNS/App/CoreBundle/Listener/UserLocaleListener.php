<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\CommonBundle\Locale\LocaleManager;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Stores the locale of the user in the session after the
 * login. This can be used by the LocaleListener afterwards.
 */
class UserLocaleListener
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var LocaleManager
     */
    private $localeManager;

    public function __construct(Session $session, LocaleManager $localeManager)
    {
        $this->session = $session;
        $this->localeManager = $localeManager;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();

        // set locale from user preferences, fallback to default locale
        $lang = $user->getLang();
        if (!in_array($lang, $this->localeManager->availableLanguages)) {
            $lang = $this->localeManager->getDefaultLocale();
        }
        $this->session->set('_locale', $lang);
        $this->localeManager->flagUserDefinedLocale($this->session);

        if (null !== $user->getTimezone()) {
            $this->session->set('_timezone', $user->getTimezone());
        }
    }
}
