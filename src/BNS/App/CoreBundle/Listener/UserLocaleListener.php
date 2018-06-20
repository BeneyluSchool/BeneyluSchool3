<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Model\User;
use BNS\CommonBundle\Locale\LocaleManager;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Translation\TranslatorInterface;

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

    /** @var TranslatorInterface  */
    protected $translator;

    public function __construct(Session $session, LocaleManager $localeManager, TranslatorInterface $translator = null)
    {
        $this->session = $session;
        $this->localeManager = $localeManager;
        $this->translator = $translator;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $token = $event->getAuthenticationToken();

        if (!$token) {
            return;
        }
        $user = $token->getUser();
        if (!$user || !$user instanceof User) {
            return;
        }

        // set locale from user preferences, fallback to default locale
        $lang = $user->getLang();
        if (!in_array($lang, $this->localeManager->availableLanguages)) {
            $lang = $this->localeManager->getDefaultLocale();
        }

        // Force user locale in session / request / translator to prevent issue with RememberMe behavior
        $request = $event->getRequest();
        $request->setLocale($lang);
        $this->session->set('_locale', $lang);
        if ($this->translator) {
            $this->translator->setLocale($lang);
        }
        $this->localeManager->flagUserDefinedLocale($this->session);

        if (null !== $user->getTimezone()) {
            $this->session->set('_timezone', $user->getTimezone());
        }
    }
}
