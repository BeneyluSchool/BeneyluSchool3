<?php

namespace BNS\App\CoreBundle\Access;

use Symfony\Component\DependencyInjection\ContainerInterface;

use BNS\App\CoreBundle\Model\User;

/**
 * @author Sylvain Lorinet
 *
 * @deprecated stop using this class inject needed service instead
 */
class BNSAccess
{
    /**
     * @var ContainerInterface
     */
    private static $container;

    /**
     * @param ContainerInterface $container
     * @deprecated
     */
    public static function setContainer(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * @return null|ContainerInterface
     * @deprecated
     */
    public static function getContainer()
    {
        return self::$container ? : null;
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

        return 'en_US';
    }

    /**
     * @return \BNS\App\CoreBundle\Model\User The connected user proxy
     * @deprecated
     */
    public static function getUser()
    {
        if (self::$container) {
            if ($token = self::$container->get('security.token_storage')->getToken()) {
                $user = $token->getUser();
                if ($user && $user instanceof User) {
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * @return boolean True if user is connected, false otherwise
     * @deprecated
     */
    public static function isConnectedUser()
    {
        return self::getUser() ? true : false;
    }

    /**
     * @return String Url courante prenant en compte les caractéristiques possibles
     * @deprecated
     */
    public static function getCurrentUrl()
    {
        return self::getContainer() ? self::getContainer()->getParameter('application_base_url') : null;
    }
}
