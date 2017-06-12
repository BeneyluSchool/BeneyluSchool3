<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\CommonBundle\Locale\LocaleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LocaleListener implements EventSubscriberInterface
{

    /**
     * @var LocaleManager
     */
    private $localeManager;

    public function __construct(LocaleManager $localeManager)
    {
        $this->localeManager = $localeManager;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     */
    public function setLocale(GetResponseEvent $event)
    {

        $request = $event->getRequest();

        // try to see if the locale has been set as a _locale routing parameter
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } elseif ($locale = $request->query->get('_locale', false)) {
            $locale = $this->localeManager->getBestLocale($locale);
            $request->attributes->set('_locale', $locale);
            $request->getSession()->set('_locale', $locale);
        } else {
            // if no explicit locale has been set on this request, use one from the session
            $session = $request->getSession();
            $cookies = $request->cookies->all();
            if ($session->has('_locale')) {
                $request->setLocale($session->get('_locale'));
            } else if (isset($cookies['_locale'])) {
                // check that locale is valid
                $locale = $this->localeManager->localeOrDefault($cookies['_locale']);
                $session->set('_locale', $locale);
                $request->setLocale($locale);
            } else if (!$this->localeManager->hasUserDefinedLocale($session))  {
                // if no user-defined locale (ie not authenticated), guess the best one and update request and session
                $this->localeManager->setBestLocale($request);
            }
        }

    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $this->setLocale($event);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 17)),
        );
    }
}
