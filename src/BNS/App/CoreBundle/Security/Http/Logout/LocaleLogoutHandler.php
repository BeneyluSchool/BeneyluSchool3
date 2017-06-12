<?php

namespace BNS\App\CoreBundle\Security\Http\Logout;

use BNS\CommonBundle\Locale\LocaleManager;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LocaleLogoutHandler
 *
 * @package BNS\App\CoreBundle\Security\Http\Logout
 */
class LocaleLogoutHandler implements LogoutHandlerInterface
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
     * This method is called by the LogoutListener when a user has requested
     * to be logged out. Usually, you would unset session variables, or remove
     * cookies, etc.
     *
     * @param Request $request
     * @param Response $response
     * @param TokenInterface $token
     */
    public function logout(Request $request, Response $response, TokenInterface $token)
    {
        $session = $request->getSession();
        $this->localeManager->unflagUserDefinedLocale($session);
        // remember user locale, for 31 days
        $response->headers->setCookie(new Cookie('_locale', $session->get('_locale'), time() + 3600 * 24 * 31));
    }

}
