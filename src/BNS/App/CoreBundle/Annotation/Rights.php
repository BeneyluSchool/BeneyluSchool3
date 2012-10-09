<?php

namespace BNS\App\CoreBundle\Annotation;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BNS\App\CoreBundle\Right\BNSRightManager;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Annotation
 */
final class Rights
{
	/**
	 * @var array<String> Permissions list
	 */
	private $rights;
	
	/**
	 * @param array $rights 
	 */
	public function __construct(array $rights)
	{
		$this->rights = preg_split('#,#', str_replace(' ', '', $rights['value']));
	}
	
	/**
	 * @param BNSRightManager $rightManager
	 * 
	 * @throws AccessDeniedHttpException In case of insufficient permission
	 */
	public function execute(BNSRightManager $rightManager)
	{
		if (!$rightManager->hasRights($this->rights)) {
			throw new AccessDeniedHttpException('Insufficient permission, you can NOT access to this page !');
		}
	}
}