<?php

namespace BNS\App\NotificationBundle\Notification\BlogBundle;

use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\CoreBundle\Model\User;

/**
 * Notification generation date : 29/05/2012 08:59:11 
 */
class BlogCommentValidatedNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'BLOG_COMMENT_VALIDATED';
	
	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param type $commentId 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $commentId, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
			'commentId' => $commentId,
		));
	}
	
	/**
	 * @param Notification $notification
	 * @param array $objects Les paramètres de la notifications
	 * 
	 * @return array Les traductions de la notification 
	 */
	public static function translate(Notification $notification, $objects)
	{
		$finalObjects = array();
		
		// Faites les modifications nécessaires à la restitution des paramètres ci-dessous
		$finalObjects['%commentId%'] = $objects['commentId'];
				
		/* 
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */
		
		return parent::getTranslation($notification, $finalObjects);
	}
}