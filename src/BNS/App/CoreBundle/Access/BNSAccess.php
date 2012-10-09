<?php

namespace BNS\App\CoreBundle\Access;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

use BNS\App\CoreBundle\Model\User;

/**
 * @author Sylvain Lorinet
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
	 */
	public static function setRequest(Request $request)
	{
		self::$request = $request;
	}

	/**
	 * @param Container $container
	 */
	public static function setContainer(Container $container)
	{
		self::$container = $container;
	}

	/**
	 * @return Request
	 */
	public static function getRequest()
	{
		return self::$request;
	}

	public static function getContainer()
	{
		return self::$container;
	}

	/**
	 * @return Session
	 */
	public static function getSession()
	{
		return self::$request->getSession();
	}

	/**
	 * @return string
	 */
	public static function getLocale()
	{
		// Locale has been set by the user
		return self::$request->getLocale();
	}
	
	/**
	 * @return \BNS\App\CoreBundle\Model\UserProxy The connected user proxy
	 */
	public static function getUser()
	{
		return null != self::getContainer()->get('security.context')->getToken() ? self::getContainer()->get('security.context')->getToken()->getUser() : null;
	} 
	
	/**
	 * @return boolean True if user is connected, false otherwise
	 */
	public static function isConnectedUser()
	{
		return self::getUser() instanceof User ? true : false;
	}
}