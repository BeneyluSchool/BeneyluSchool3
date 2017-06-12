<?php

namespace BNS\App\NotificationBundle\Model;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class NotificationCollection
{
	/**
	 * @var array<Notification> 
	 */
	private $collection;

	private $announcementsCount = 0;
	
	/**
	 * @param array<Notification> $collection
	 * @param NotificationSettingsCollection $settings
	 * @param int $announcementsCount
	 */
	public function __construct($collection, NotificationSettingsCollection $settings, $announcementsCount = 0)
	{
		$this->collection = array();
		
		foreach ($collection as $item) {
			if ($settings->isEnabled($item)) {
				if ($item->getNotificationType()->getModuleUniqueName() == 'NOTIFICATION') {
					// Considering that all notifications with bundle name "NOTIFICATION" are announces
					$this->collection['announce'][$item->getNotificationType()->getModuleUniqueName()][] = $item;
				}
				elseif (null != $item->getGroupId()) {
					$this->collection[$item->getGroupId()][$item->getNotificationType()->getModuleUniqueName()][] = $item;
				}
				else {
					// Personnal notifications : non context modules
					$this->collection['personnal'][$item->getNotificationType()->getModuleUniqueName()][] = $item;
				}
			}
		}

		$this->announcementsCount = $announcementsCount;
	}
	
	/**
	 * Get count notification, read & unread
	 * 
	 * @param int $groupId
	 * @param string $moduleUniqueName
	 * 
	 * @return int
	 */
	public function getCount($groupId = null, $moduleUniqueName = null)
	{
		if (null != $groupId && null == $moduleUniqueName && isset($this->collection[$groupId])) {
			return $this->countModule($this->collection[$groupId]);
		}
		elseif (null != $moduleUniqueName && isset($this->collection[$groupId]) && isset($this->collection[$groupId][$moduleUniqueName])) {
			return count($this->collection[$groupId][$moduleUniqueName]);
		}
		elseif (null == $groupId && null == $moduleUniqueName) {
			return $this->getTotalCount($this->collection);
		}
		
		return 0;
	}
	
	/**
	 * @param array $group
	 * 
	 * @return int
	 */
	private function countModule($group)
	{
		$count = 0;
		foreach ($group as $modules) {
			foreach ($modules as $notification) {
				$count++;
			}
		}
		
		return $count;
	}
	
	/**
	 * Get all correction notification count, read & unread
	 * 
	 * @return int 
	 */
	public function getCorrectionCount()
	{
		$count = 0;
		foreach ($this->collection as $colGroup) {
			foreach ($colGroup as $colModule) {
				foreach ($colModule as $item) {
					if ($item->getNotificationType()->isCorrection()) {
						$count++;
					}
				}
			}
		}
		
		return $count;
	}

	public function getAnnouncementCount()
	{
		return $this->announcementsCount;
	}
	
	/**
	 * Get all notification count, only unread
	 * 
	 * @return int 
	 */
	public function getTotalCount()
	{
		$count = 0;
		foreach ($this->collection as $colGroup) {
			foreach ($colGroup as $colModule) {
				foreach ($colModule as $item) {
					$count++;
				}
			}
		}

		$count += $this->getAnnouncementCount();
		
		return $count;
	}
}
