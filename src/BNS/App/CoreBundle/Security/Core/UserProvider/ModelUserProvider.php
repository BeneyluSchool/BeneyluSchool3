<?php

namespace BNS\App\CoreBundle\Security\Core\UserProvider;

use BNS\App\CoreBundle\Security\Propel\ModelUserProvider as BaseModelUserProvider;

class ModelUserProvider extends BaseModelUserProvider
{
	/**
     * Default constructor
     *
     * @param $class        The User model class.
     * @param $proxyClass   The Proxy class name for the model class.
     * @param $property     The property to use to retrieve a user.
     */
	public function __construct($class, $property = null)
	{
		parent::__construct($class, 'BNS\App\CoreBundle\Model\UserProxy', $property);
	}
}