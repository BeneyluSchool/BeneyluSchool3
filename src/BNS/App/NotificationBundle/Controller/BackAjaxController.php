<?php

namespace BNS\App\NotificationBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\NotificationBundle\Model\NotificationSettingsQuery;
use BNS\App\NotificationBundle\Model\NotificationSettings;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion")
 */
class BackAjaxController extends AbstractNotificationController
{
	/**
	 * @Route("/switch/{contextGroupId}/{engine}/{moduleUniqueName}", name="notification_manager_switch")
	 */
	public function switchAction($contextGroupId, $engine, $moduleUniqueName)
	{
		$user = $this->getUser();
		
		// Security process
		$this->isSecure($contextGroupId, $moduleUniqueName);
		
		$query = NotificationSettingsQuery::create('n')
			->where('n.UserId = ?', $user->getId())
			->where('n.ModuleUniqueName = ?', $moduleUniqueName)
			->where('n.NotificationEngine = ?', $engine)
		;
		
		if ('personnal' == $contextGroupId) {
			$query->where('n.ContextGroupId IS NULL');
		}
		else {
			$query->where('n.ContextGroupId = ?', $contextGroupId);
		}
		
		$settings = $query->findOne();
		
		if (null == $settings) {
			$settings = new NotificationSettings();
			$settings->setContextGroupId($contextGroupId != 'personnal' ? $contextGroupId : null);
			$settings->setUserId($user->getId());
			$settings->setModuleUniqueName($moduleUniqueName);
			$settings->setNotificationEngine($engine);
			$settings->save();
		}
		else {
			$settings->delete();
		}
		
		return new Response();
	}
	
	/**
	 * @Route("/switch/{contextGroupId}/{engine}", name="notification_manager_switch_group")
	 */
	public function switchGroup($contextGroupId, $engine)
	{
		$user = $this->getUser();
		
		// Security process
		$userGroups = $this->isSecure($contextGroupId);
		
		$query = NotificationSettingsQuery::create('n')
			->where('n.UserId = ?', $user->getId())
			->where('n.NotificationEngine = ?', $engine)
		;
		
		if ('personnal' == $contextGroupId) {
			$query->where('n.ContextGroupId IS NULL');
		}
		else {
			$query->where('n.ContextGroupId = ?', $contextGroupId);
		}
		
		$settings = $query->find();
		$modules = array();
		
		if ('personnal' == $contextGroupId) {
			foreach ($userGroups as $group) {
				foreach ($group->getGroupType()->getModules() as $module) {
					if (!$module->isContextable()) {
						$modules[$module->getUniqueName()] = $module;
					}
				}
			}
		}
		else {
			foreach ($userGroups as $group) {
				if ($group->getId() == $contextGroupId) {
					$modules = $group->getGroupType()->getModules();
					break;
				}
			}
		}
		
		// Si toutes les modules ne sont pas désactivés, on désactive tout
		if (null == $settings || count($settings) < count($modules)) {
			$settings->delete();
			
			foreach ($modules as $module) {
				$settings = new NotificationSettings();
				$settings->setContextGroupId($contextGroupId != 'personnal' ? $contextGroupId : null);
				$settings->setUserId($user->getId());
				$settings->setModuleUniqueName($module->getUniqueName());
				$settings->setNotificationEngine($engine);
				$settings->save();
				
				$settings = null;
			}
		}
		// Sinon on réactive tout
		else {
			$settings->delete();
		}
		
		return new Response();
	}
}