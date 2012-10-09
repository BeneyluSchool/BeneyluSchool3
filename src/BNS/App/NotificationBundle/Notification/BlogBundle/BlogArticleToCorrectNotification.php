<?php

namespace BNS\App\NotificationBundle\Notification\BlogBundle;

use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;

/**
 * Notification generation date : 29/05/2012 08:56:47 
 */
class BlogArticleToCorrectNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'BLOG_ARTICLE_TO_CORRECT';
	
	/**
	 * @param User $targetUser L'utilisateur qui va recevoir la notification
	 * @param type $articleId 
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(User $targetUser, $articleId, $groupId = null)
	{
		parent::__construct();
		$this->init($targetUser, $groupId, self::NOTIFICATION_TYPE, array(
			'articleId' => $articleId,
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
		$article = BlogArticleQuery::create()
			->add(BlogArticlePeer::ID, $objects['articleId'])
		->findOne();
		
		if (null == $article) {
			throw new NotFoundHttpException('The article with ID ' . $objects['articleId'] . ' is not found !');
		}
		
		$finalObjects = array();
		$finalObjects['%articleTitle%']	= $article->getTitle();
		$finalObjects['%articleUrl%']	= BNSAccess::getContainer()->get('router')->generate('blog_manager_edit_article', array(
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