<?php

namespace BNS\App\InstallBundle\Exception;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class AuthRankAlreadyInstalled extends \RuntimeException
{
	/**
	 * @var array<String>
	 */
	private $rankResponse;

	/**
	 * @param string $message
	 * @param array<String> $rankResponse
	 */
	public function __construct($message, $rankResponse)
	{
		parent::__construct($message);

		$this->rankResponse = $rankResponse;
	}

	/**
	 * @return array<String>
	 */
	public function getRankResponse()
	{
		return $this->rankResponse;
	}
}