<?php

namespace BNS\App\CoreBundle\Annotation;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BNS\App\CoreBundle\Right\BNSRightManager;

/**
 * @author Eric Chau <eric.chau@pixel-cookers.com>
 * 
 * @Annotation
 */
final class RightsSomeWhere
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
		$this->rights = is_array($rights['value']) ? $rights['value'] : array($rights['value']);
	}
	
	/**
	 * @param BNSRightManager $rightManager
	 * 
	 * @throws AccessDeniedHttpException In case of insufficient permission
	 */
	public function execute(BNSRightManager $rightManager, $uri)
	{
		if (!$rightManager->hasRightsSomeWhere($this->rights)) {
			throw new AccessDeniedHttpException('Insufficient permission, you can NOT access to this page for URI : "' . $uri . '" !');
		}
	}
}