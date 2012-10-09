<?php

namespace BNS\App\NotificationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\NotificationBundle\Model\NotificationSettingsQuery;
use BNS\App\NotificationBundle\Model\NotificationSettingsCollection;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion")
 */
class BackController extends Controller
{
	/**
	 * @Route("/", name="BNSAppNotificationBundle_back")
	 */
	public function indexAction()
	{
		$user = $this->getUser();
		$this->get('bns.user_manager')->setUser($user);
		$userGroups = $this->get('bns.user_manager')->getGroupsUserBelong();
		
		// Récupération des settings
		$notificationSettings = NotificationSettingsQuery::create('n')
			->where('n.UserId = ?', $user->getId())
		->find();
		
		$context = $this->get('bns.right_manager')->getContext();
		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();
		
		$isPupil = false;
		foreach ($context['roles'] as $role) {
			if ($role == $pupilRole->getId()) {
				$isPupil = true;
				break;
			}
		}
		
		$personnalModules = array();
		foreach ($userGroups as $group) {
			foreach ($group->getGroupType()->getModules() as $module) {
				if (!$module->isContextable()) {
					$personnalModules[$module->getUniqueName()] = $module;
				}
			}
		}
		
		return $this->render('BNSAppNotificationBundle:Back:index' . ($isPupil ? '_pupil' : '') . '.html.twig', array(
			'userGroups'		=> $userGroups,
			'settings'			=> new NotificationSettingsCollection($notificationSettings),
			'personnalModules'	=> $personnalModules
		));
	}
}