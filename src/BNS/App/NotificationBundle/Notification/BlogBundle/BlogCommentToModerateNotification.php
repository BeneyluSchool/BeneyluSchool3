<?php

namespace BNS\App\NotificationBundle\Notification\BlogBundle;

use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Model\BlogArticleCommentPeer;

/**
 * Notification generation date : 29/05/2012 08:58:09 
 */
class BlogCommentToModerateNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'BLOG_COMMENT_TO_MODERATE';
	
	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param type $articleId 
	 * @param type $commentId 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $articleId, $commentId, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
			'articleId' => $articleId,
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
		$comment = BlogArticleCommentQuery::create()
			->joinWith('BlogArticle')
			->add(BlogArticleCommentPeer::ID, $objects['commentId'])
		->findOne();
		
		if (null == $comment) {
			throw new NotFoundHttpException('The comment with ID ' . $objects['commentId'] . ' is not found !');
		}
		
		$finalObjects = array();
		$finalObjects['%articleTitle%']	= $comment->getBlogArticle()->getTitle();
		
		// FIXME perma link comment front
		$finalObjects['%commentUrl%']	= BNSAccess::getContainer()->get('router')->generate('blog_manager_edit_article', array(
			'articleId' => $objects['articleId']
		));
				
		/* 
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */
		
		return parent::getTranslation($notification, $finalObjects);
	}
}