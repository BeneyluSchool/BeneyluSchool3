<?php

namespace BNS\App\NotificationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractNotificationController extends Controller
{
	/**
	 * @param int	 $contextGroupId
	 * @param string $moduleUniqueName
	 */
	protected function isSecure($contextGroupId, $moduleUniqueName = null)
	{
		$user = $this->getUser();
		$this->get('bns.user_manager')->setUser($user);
		$userGroups = $this->get('bns.user_manager')->getGroupsUserBelong();
		
		if (null == $contextGroupId && $moduleUniqueName == 'NOTIFICATION' || // Announces
			$contextGroupId == 'personnal')
		{
			return $userGroups;
		}
		
		if (null == $moduleUniqueName) {
			$this->isSecureForGroup($userGroups, $contextGroupId);
		}
		else {
			$this->isSecureForGroupModule($userGroups, $contextGroupId, $moduleUniqueName);
		}
		
		return $userGroups;
	}
	
	/**
	 * @param array<Group> $userGroups
	 * @param int		   $contextGroupId
	 * 
	 * @throws AccessDeniedHttpException
	 */
	private function isSecureForGroup($userGroups, $contextGroupId)
	{
		// Security process
		$found = false;
		foreach ($userGroups as $group) {
			if ($contextGroupId == $group->getId()) {
				$found = true;
				break;
			}
		}
		
		if (!$found) {
			throw new AccessDeniedHttpException('You can NOT edit a notification from a foreign group !');
		}
	}
	
	/**
	 * @param array<Group> $userGroups
	 * @param int		   $contextGroupId
	 * @param string	   $moduleUniqueName
	 * 
	 * @throws AccessDeniedHttpException
	 */
	private function isSecureForGroupModule($userGroups, $contextGroupId, $moduleUniqueName)
	{
		// Security process
		$found = false;
		foreach ($userGroups as $group) {
			if ($contextGroupId == $group->getId()) {
				foreach ($group->getGroupType()->getModules() as $module) {
					if ($moduleUniqueName == $module->getUniqueName()) {
						$found = true;
						break;
					}
				}
				
				if ($found) {
					break;
				}
				
				throw new AccessDeniedHttpException('You can NOT edit a notification that you do NOT have access to the module : ' . $moduleUniqueName);
			}
		}
		
		if (!$found) {
			throw new AccessDeniedHttpException('You can NOT edit a notification from a foreign group !');
		}
	}
}