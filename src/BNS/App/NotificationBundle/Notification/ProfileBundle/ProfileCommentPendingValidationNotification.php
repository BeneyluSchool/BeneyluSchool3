<?php

namespace BNS\App\NotificationBundle\Notification\ProfileBundle;

use BNS\App\CoreBundle\Model\ProfileCommentQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Notification generation date : 28/01/2013 18:35:58 
 */
class ProfileCommentPendingValidationNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'PROFILE_COMMENT_PENDING_VALIDATION';
	
	/**
	 * @param ContainerInterface $container Services container
	 * @param type $comment_id 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $comment_id, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'comment_id' => $comment_id,
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
		// Le container est accessible grâce à l'attribut statique "self::$container"
		
		$comment = ProfileCommentQuery::create('c')
			->joinWith('User u')
			->joinWith('ProfileFeed pf')
			->joinWith('pf.Profile p')
			->joinWith('p.User u2')
			->where('c.Id = ?', $objects['comment_id'])
		->findOne();
		
		if (null == $comment) {
			throw new \RuntimeException('The comment with id : ' . $objects['comment_id'] . ' is NOT found !');
		}
		
		$finalObjects['%sender_full_name%']  = $comment->getAuthor()->getFullName();
		$finalObjects['%profile_full_name%'] = $comment->getObject()->getProfile()->getUser()->getFullName();
		$finalObjects['%comment_route%']	 = $notification->getBaseUrl() . self::$container->get('cli.router')->generate('profile_manager_feed_visualisation', array(
			'feedId' => $comment->getObjectId()
		));
				
		/* 
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */
		
		return parent::getTranslation($notification, $finalObjects);
	}
}