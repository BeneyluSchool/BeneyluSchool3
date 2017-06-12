<?php

namespace BNS\App\InstallBundle\Exception;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class AuthPermissionAlreadyInstalled extends \RuntimeException
{
	/**
	 * @var array<String>
	 */
	private $permissionResponse;

	/**
	 * @param string $message
	 * @param array<String> $permissionResponse
	 */
	public function __construct($message, $permissionResponse)
	{
		parent::__construct($message);

		$this->permissionResponse = $permissionResponse;
	}

	/**
	 * @return array<String>
	 */
	public function getPermissionResponse()
	{
		return $this->permissionResponse;
	}
}