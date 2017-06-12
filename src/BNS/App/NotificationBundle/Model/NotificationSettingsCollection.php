<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\Module;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class NotificationSettingsCollection
{
	/**
	 * @var array<Integer, array<NotificationSettings>> <GroupId, array<NotificationSettings>>
	 */
	private $collection;

	/**
	 * @param array<NotificationSettings> $collection
	 */
	public function __construct($collection)
	{
		$this->collection = array();
		
		foreach ($collection as $col) {
			if ($col->getModuleUniqueName() == 'NOTIFICATION') {
				$this->collection['announce'][] = $col;
			}
			elseif (null == $col->getContextGroupId()) {
				$this->collection['personnal'][] = $col;
			}
			else {
				$this->collection[$col->getContextGroupId()][] = $col;
			}
		}
	}
	
	/**
	 * @param int			$groupId
	 * @param array<Module>	$modules
	 * @param int			$userId
	 * @param string		$engine
	 * 
	 * @return boolean 
	 */
	public function isUserActivated($groupId, $modules, $userId, $engine = null)
	{
		// The group does NOT exist, all notifications are activated
		if (!isset($this->collection[$groupId])) {
			return true;
		}
		
		foreach ($modules as $module) {
			if ($this->isModuleForUserActivated($groupId, $userId, $module->getUniqueName(), $engine)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * @param int		$groupId
	 * @param int		$userId
	 * @param string	$moduleUniqueName
	 * @param string	$engine
	 * 
	 * @return boolean
	 */
	public function isModuleForUserActivated($groupId, $userId, $moduleUniqueName, $engine = null)
	{
		// The group does NOT exist, all notifications are activated
		if (!isset($this->collection[$groupId])) {
			return true;
		}
		
		foreach ($this->collection[$groupId] as $item) {
			if ($item->getUserId() == $userId && $item->getModuleUniqueName() == $moduleUniqueName) {
				if (null == $engine) {
					return false;
				}
				elseif ($item->isEngine($engine)) {
					return false;
				}
			}
		}
		
		return true;
	}
	
	/**
	 * @param \BNS\App\NotificationBundle\Model\Notification $notification
	 * 
	 * @return boolean 
	 */
	public function isEnabled(Notification $notification, $engine = 'SYSTEM')
	{
		if (!isset($this->collection[$notification->getGroupId()])) {
			return true;
		}
		
		foreach ($this->collection[$notification->getGroupId()] as $item) {
			if ($item->getModuleUniqueName() == $notification->getNotificationType()->getModuleUniqueName() &&
				$item->getNotificationEngine() == $engine)
			{
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * 
	 * @param \BNS\App\CoreBundle\Model\Group $group
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * 
	 * @return boolean
	 */
	public function isGroupEnabled(Group $group, $isContextable = null)
	{
		foreach ($group->getGroupType()->getModules($isContextable) as $module) {
			if ($this->isModuleEnabled($group->getId(), $module)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Only for personnal modules
	 * 
	 * @param array<Module> $modules
	 * 
	 * @return boolean
	 */
	public function isPersonnalModulesEnabled($modules = array())
	{
		foreach ($modules as $module) {
			if ($this->isModuleEnabled('personnal', $module)) {
				return true;
			}
		}
		
		return true;
	}
	
	/**
	 * @param \BNS\App\NotificationBundle\Model\Group $group
	 * @param \BNS\App\CoreBundle\Model\Module $module
	 * @param string $engine
	 * 
	 * @return boolean
	 */
	public function isModuleEnabled($groupId, Module $module, $engine = 'SYSTEM')
	{
		// The group does NOT exist, all notifications are activated
		if (!isset($this->collection[$groupId])) {
			return true;
		}
		
		foreach ($this->collection[$groupId] as $item) {
			if ($item->getModuleUniqueName() == $module->getUniqueName() &&
				$item->getNotificationEngine() == $engine)
			{
				return false;
			}
		}

		return true;
	}
}