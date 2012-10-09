<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\NotificationBundle\TranslateFactory\NotificationTranslateFactory;
use BNS\App\NotificationBundle\Model\om\BaseNotification;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\NotificationBundle\Model\NotificationSettingsQuery;

/**
 * Skeleton subclass for representing a row from the 'notification' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.NotificationBundle.Model
 */
class Notification extends BaseNotification
{
	const ENGINE_EMAIL		= 'EMAIL';
	const ENGINE_SYSTEM		= 'SYSTEM';
	
	/**
	 * @param type $targetUserId Utilisateur cible
	 * @param type $groupId ID du groupe de l'utilisateur cible
	 */
	public function init(User $targetUser, $groupId, $notificationType, array $objects)
	{
		$this->aUser = $targetUser;
		$this->setGroupId($groupId);
		$this->setTargetUserId($targetUser->getId());
		$this->setNotificationTypeUniqueName($notificationType);
		$this->setDate(time());
		$this->setObjects(serialize($objects));
	}
	
	/**
	 * @param string $notificationEngine Si spécifié, la notification ne sera envoyée que sur cet engine.
	 */
	public function send($notificationEngine = null)
	{
		// On ne traite pas l'envoi par email des utilisateurs qui n'ont pas d'email
		if (null == $this->aUser->getEmail()) {
			$notificationEngine == self::ENGINE_SYSTEM;
		}
		
		// Si ce n'est pas une annonce
		if (null != $this->getGroupId()) {
			// On récupère tous les engines désactivés pour le groupe & le rôle 
			$notificationSettings = NotificationSettingsQuery::create('n')
				->where('n.UserId = ?', $this->getTargetUserId())
				->where('n.ContextGroupId = ?', $this->getGroupId())
				->where('n.ModuleUniqueName = ?', $this->getNotificationType()->getModuleUniqueName())
			->find();
		}
		else {
			// Si c'est une annonce, on récupère les cas où c'est désactivé
			$notificationSettings = NotificationSettingsQuery::create('n')
				->where('n.ContextGroupId IS NULL')
				->where('n.UserId = ?', $this->getTargetUserId())
			->find();
		}
		
		// On récupère les unique names
		$allNotificationEngines = array(
			self::ENGINE_EMAIL	=> true,
			self::ENGINE_SYSTEM	=> true
		);
		
		$disabledNotificationEngineUniqueNames = array();
		
		// On récupère les engines désactivés pour le type de notification en cours
		if (null != $this->getNotificationType()->getDisabledEngine()) {
			$disabledNotificationTypeEngine = str_split(',', str_replace(' ', '', $this->getNotificationType()->getDisabledEngine()));
			foreach ($disabledNotificationTypeEngine as $engineUniqueName) {
				$disabledNotificationEngineUniqueNames[$engineUniqueName] = true;
			}
		}

		// On récupère les engines désactivés depuis les settings
		foreach ($notificationSettings as $engine) {
			$disabledNotificationEngineUniqueNames[$engine->getNotificationEngine()] = true;
		}
		
		// Si on spécifie un engine
		if (null != $notificationEngine) {
			// L'engine est désactivé pour le destinaire, on s'arrête là
			if (in_array($notificationEngine, $disabledNotificationEngineUniqueNames)) {
				$this->requestEngine($notificationEngine, false);
				return;
			}
		}
		// Si au moins un engine est désactivé on envoi sur ceux activés
		else if (count($disabledNotificationEngineUniqueNames) > 0) {
			foreach ($allNotificationEngines as $uniqueName => $engine) {
				if (isset($disabledNotificationEngineUniqueNames[$uniqueName])) {
					$this->requestEngine($uniqueName, false);
				}
				else {
					$this->requestEngine($uniqueName);
				}
			}
		}
		// Sinon on envoi toute la sauce
		else {
			foreach ($allNotificationEngines as $uniqueName => $engine) {
				$this->requestEngine($uniqueName);
			}
		}
	}
	
	/**
	 * La notification est prête pour être envoyée grâce aux engines.
	 * Si l'engine est activé on traite la notification, sinon on la traite d'une manière différente voire pas du tout.
	 * 
	 * @param string $engine Notification Engine
	 * @param boolean $engineIsActivated 
	 */
	public function requestEngine($engine, $engineIsActivated = true)
	{
		switch ($engine)
		{
			case self::ENGINE_SYSTEM:
				$this->sendSystemEngine($engineIsActivated);
			break;

			case self::ENGINE_EMAIL:
				if ($engineIsActivated) {
					$this->sendEmailEngine();
				}
			break;
		}
	}
	
	/**
	 * @param boolean $engineIsActivated 
	 */
	private function sendSystemEngine($engineIsActivated)
	{
		// On n'affiche pas le callback de la notification : on la met directement comme lue
		if (!$engineIsActivated) {
			$this->setIsNew(false);
		}
		
		$this->save();
	}
	
	/**
	 * Email
	 */
	private function sendEmailEngine()
	{
		$translation = NotificationTranslateFactory::translate($this, self::ENGINE_EMAIL);
		$variables = array(
			'title'			=> $translation['title'],
			'notification'	=> $translation['content']
		);
		
		BNSAccess::getContainer()->get('bns.mailer')->sendUser('NOTIFICATION', $variables, $this->aUser);
	}
	
	/**
	 * @param string $engine
	 * 
	 * @return array 
	 */
	public function trans($engine = null)
	{
		return NotificationTranslateFactory::translate($this, $engine);
	}
	
	/**
	 * @param Notification $notification
	 * @param type $finalObjects
	 * @param string|null $translationName
	 * 
	 * @return array 
	 */
	protected static function getTranslation(Notification $notification, $finalObjects, $translationName = null)
	{
		if (null == $translationName) {
			$translationNames = array(
				$notification->getNotificationTypeUniqueName() . '.EMAIL.title',
				$notification->getNotificationTypeUniqueName() . '.EMAIL.content',
				$notification->getNotificationTypeUniqueName() . '.SYSTEM.content',
			);
			
			$translations = array();
			foreach ($translationNames as $translationName) {
				$engine = preg_split('#\.#', $translationName);
				$translations[$engine[1]][$engine[2]] = BNSAccess::getContainer()->get('translator')->trans($translationName, $finalObjects, $notification->getNotificationTypeUniqueName());
			}
				
			return $translations;
		}
		
		return BNSAccess::getContainer()->get('translator')->trans($translationName, $finalObjects, $notification->getNotificationTypeUniqueName());
	}
}