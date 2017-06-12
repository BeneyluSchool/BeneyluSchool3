<?php

namespace BNS\App\InstallBundle\Exception;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class AuthModuleAlreadyInstalled extends \RuntimeException
{
	/**
	 * @var array<String>
	 */
	private $moduleResponse;

	/**
	 * @param string $message
	 * @param array<String> $moduleResponse
	 */
	public function __construct($message, $moduleResponse)
	{
		parent::__construct($message);

		$this->moduleResponse = $moduleResponse;
	}

	/**
	 * @return array<String>
	 */
	public function getModuleResponse()
	{
		return $this->moduleResponse;
	}
}