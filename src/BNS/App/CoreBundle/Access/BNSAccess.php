<?php

namespace BNS\App\CoreBundle\Access;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @author Sylvain Lorinet
 *
 * @deprecated stop using this class inject needed service instead
 */
class BNSAccess
{
    /**
     * @var Request
     */
    private static $request;

    /**
     * @var Container
     */
    private static $container;

    /**
     * @param Request $request
     * @deprecated
     */
    public static function setRequest(Request $request)
    {
        self::$request = $request;
    }

    /**
     * @param Container $container
     * @deprecated
     */
    public static function setContainer(Container $container)
    {
        self::$container = $container;
    }

    /**
     * @return Request
     * @deprecated
     */
    public static function getRequest()
    {
        return self::$request;
    }

    /**
     * @return null|Container
     * @deprecated
     */
    public static function getContainer()
    {
        if (isset(self::$container)) {
            return self::$container;
        }

        return null;
    }

    /**
     * @return Session
     * @deprecated
     */
    public static function getSession()
    {
        return self::$request->getSession();
    }

    /**
     * @return string
     * @deprecated
     */
    public static function getLocale()
    {
        if (self::$container) {
            $locale = self::$container->get('translator')->getLocale();

            if (!$locale) {
                return self::$container->getParameter('kernel.default_locale');
            }

            return $locale;
        }

        if (self::$request) {
            // Locale has been set by the user
            return self::$request->getLocale();
        }

        return 'en_US';
    }

    /**
     * @return \BNS\App\CoreBundle\Model\UserProxy The connected user proxy
     * @deprecated
     */
    public static function getUser()
    {
        return null != self::getContainer()->get('security.context')->getToken() ? self::getContainer()->get('security.context')->getToken()->getUser(
        ) : null;
    }

    /**
     * @return boolean True if user is connected, false otherwise
     * @deprecated
     */
    public static function isConnectedUser()
    {
        if (isset(self::$container)) {
            return self::getUser() instanceof User ? true : false;
        }

        return false;
    }

    /**
     * @return String Url courante prenant en compte les caractéristiques possibles
     * @deprecated
     */
    public static function getCurrentUrl()
    {
        return self::getContainer()->getParameter('application_base_url');
    }
}
