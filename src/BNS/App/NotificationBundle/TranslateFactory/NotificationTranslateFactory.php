<?php

namespace BNS\App\NotificationBundle\TranslateFactory;

use BNS\App\NotificationBundle\Model\Notification;

/**
 * @author Sylvain Lorinet
 */
class NotificationTranslateFactory
{
	/**
	 * @var $cache array	Garde les traductions de notification en cache pour éviter de les recharger si on appelle plusieurs fois la traduction
	 *						Le hash se forme de cette manière : [Notification type unique name]_[Target user id](_[Sender user id).
	 * 
	 * @example : "DEMO_COMMENT_14_145" ou "DEMO_COMMENT_14" si sender_user_id est nul
	 */
	private static $cache = array();
	
	public static function translate(Notification $notification, $engine = null)
	{
		// Voir attribut $cache
		$cacheName = $notification->getNotificationTypeUniqueName() . '_' . $notification->getTargetUserId();
		
		// Existe déjà dans le cache ?
		if (isset(self::$cache[$cacheName])) {
			if (null != $engine) {
				if (!isset(self::$cache[$cacheName][$engine])) {
					throw new \InvalidArgumentException('Unkwown notification engine for name : ' . $engine . ' !');
				}
				else {
					return self::$cache[$cacheName][$engine];
				}
			}
			else {
				return self::$cache[$cacheName];
			}
		}
		
		$typeNotification = self::getNamespace($notification->getNotificationTypeUniqueName(), $notification->getNotificationType()->getModuleUniqueName());
		$translation = $typeNotification::translate($notification, unserialize($notification->getObjects()));
		
		// Enregistrement en cache
		self::$cache[$cacheName] = $translation;
		
		if (null != $engine) {
			if (!isset($translation[$engine])) {
				throw new \InvalidArgumentException('Unkwown notification engine for name : ' . $engine . ' !');
			}
			
			return $translation[$engine];
		}
		
		return $translation;
	}
	
	/**
	 * @param type $notificationTypeUniqueName 
	 * @param type $bundleName 
	 * 
	 * @return string The namespace, ex: BNS\\App\\NotificationBundle\\Notification\\ExampleBundle\\ExampleNotification
	 */
	public static function getNamespace($notificationTypeUniqueName, $moduleUniqueName)
	{
		$className	= self::pascalize($notificationTypeUniqueName);
		$bundleName	= self::pascalize($moduleUniqueName);
		
		return 'BNS\\App\\NotificationBundle\\Notification\\' . $bundleName . 'Bundle\\' . $className . 'Notification';
	}
	
	/**
	 * @param string $string The string
	 * 
	 * @return string Convert a string from template "MODULE_TYPE_NOTIFICATION" to template "ModuleTypeNotification"
	 */
	public static function pascalize($string)
	{
		$words = preg_split('#_#', $string);
		$result = '';
		
		foreach ($words as $word) {
			$result .= ucfirst(strtolower($word));
		}
		
		return $result;
	}
}