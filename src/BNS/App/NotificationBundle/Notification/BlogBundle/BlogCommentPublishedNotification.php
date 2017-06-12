<?php

namespace BNS\App\NotificationBundle\Notification\BlogBundle;

use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\NotificationBundle\Model\Notification;
use BNS\App\NotificationBundle\Model\NotificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Notification generation date : 28/01/2013 15:33:57
 */
class BlogCommentPublishedNotification extends Notification implements NotificationInterface
{
	const NOTIFICATION_TYPE = 'BLOG_COMMENT_PUBLISHED';

	/**
	 * @param ContainerInterface $container Services container
	 * @param int $comment_id
	 * @param int $groupId L'ID du groupe de l'utilisateur qui va recevoir la notification
	 */
	public function __construct(ContainerInterface $container, $comment_id, $groupId = null)
	{
		parent::__construct();
		$this->init($container, $groupId, self::NOTIFICATION_TYPE, array(
			'comment_id' => $comment_id,
            'groupId' => $groupId
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

		$comment = BlogArticleCommentQuery::create('c')
			->joinWith('User')
			->joinWith('BlogArticle')
			//->join('BlogArticle.Blog')
			->where('c.Id = ?', $objects['comment_id'])
		->findOne();

        $group = GroupQuery::create()->findPk($objects['groupId']);
        if (null == $group) {
            $finalObjects['%classLabel%'] = null;
        } else {
            $finalObjects['%classLabel%'] = "[" . $group->getLabel() . "] ";
        }
		if (null == $comment) {
			throw new \RuntimeException('The comment with id : ' . $objects['comment_id'] . ' is NOT found !');
		}

		$finalObjects['%article_title%']	= $comment->getObject()->getTitle();
		$finalObjects['%sender_full_name%'] = $comment->getAuthor()->getFullName();
		$finalObjects['%comment_route%']	= $notification->getBaseUrl() . self::$container->get('cli.router')->generate('blog_article_permalink', array(
			'slug'	=> $comment->getObject()->getSlug()
		));

		/*
		 * Vous pouvez aussi créer un tableau de traduction selon vos propres règles de nommage grâce
		 * à cette méthode : parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.REGLE DE NOMMAGE')
		 * Exemple : $results['NEW_ENGINE'] = parent::getTranslation($notification, $finalObjects, self::NOTIFICATION_TYPE . '.NEW_ENGINE.content'); return $results;
		 */

		return parent::getTranslation($notification, $finalObjects);
	}
}
