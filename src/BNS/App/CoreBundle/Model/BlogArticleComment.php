<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CommentBundle\Comment\CommentInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseBlogArticleComment;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogCommentPendingValidationNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogCommentPublishedForAuthorNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogCommentPublishedNotification;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BlogArticleComment extends BaseBlogArticleComment implements CommentInterface
{
	/**
	 * @var BlogArticleComment
	 */
	private $commentBeforeSave;

	/**
	 *
	 */
	public function __construct()
	{
		$this->applyDefaultValues();
		$this->setDate(time());
	}

	/**
	 * Simple shortcut
	 *
	 * @return User The author
	 */
	public function getAuthor()
	{
		return $this->getUser();
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return 'ID: ' . $this->getId() . ', OBJECT_ID: ' . $this->getObjectId();
	}

	/**
	 * @return User
	 */
	public function getObjectAuthor()
	{
		return $this->getBlogArticle()->getAuthor();
	}

	/**
	 * @return BlogArticle
	 */
	public function getObject()
	{
		return $this->getBlogArticle();
	}

	/**
	 * @return array
	 */
	public function getObjectRoute()
	{
		return array(
			'blog_manager_article_visualisation',
			array(
				'articleSlug' => $this->getObject()->getSlug()
			)
		);
	}

	/**
	 * @return string
	 */
	public static function getCommentAdminRight()
	{
		return 'BLOG_ADMINISTRATION';
	}

    public static function getCommentFilter()
    {
        return BlogArticleCommentPeer::BLOG_ID;
    }

	/**
	 * @param \PropelPDO $con
	 *
	 * @return int Affected rows
	 */
	public function save(\PropelPDO $con = null, $skipNotification = false)
	{
		$affectedRows = parent::save($con);
		$container	  = BNSAccess::getContainer();

		// If $container == null, this method is called by CLI
		if ($skipNotification || null == $container) {
			return $affectedRows;
		}

		$currentGroup = $container->get('bns.right_manager')->getCurrentGroup();

		if (null == $this->commentBeforeSave) {
			$this->commentBeforeSave = new BlogArticleComment();
		}

        $blogGroup = $this->getBlog()->getGroup();

		if ($this->commentBeforeSave->isNew() && $this->getStatus() == 'VALIDATED' ||
			!$this->commentBeforeSave->isNew() && $this->commentBeforeSave->getStatus() != 'VALIDATED' && $this->getStatus() == 'VALIDATED') {
			// Nouveau commentaire publié PAR user POUR utilisateur(s) actif(s) sur la publication
			$users = UserQuery::create('u')
				->join('BlogArticleComment bac')
				->where('bac.ObjectId = ?', $this->getObjectId())
				->where('bac.Status <> ?', 'REFUSED')
				->groupBy('u.Id')
			->find();

			$objectAuthor = UserQuery::create('u')
				->join('BlogArticle ba')
				->where('ba.Id = ?', $this->getObjectId())
			->findOne();



			$container->get('notification_manager')->send($users, new BlogCommentPublishedNotification($container, $this->getId(), $blogGroup->getId()), array(
				$this->getAuthor(),
				$objectAuthor
			));

			// Nouveau commentaire publié PAR user POUR auteur
			$container->get('notification_manager')->send($objectAuthor, new BlogCommentPublishedForAuthorNotification($container, $this->getId(), $blogGroup->getId()), $this->getAuthor());
		}
		elseif ($this->commentBeforeSave->isNew() && $this->getStatus() == 'PENDING_VALIDATION') {
			// Nouveau commentaire en modération PAR user POUR enseignants (via permission)
			$container->get('notification_manager')->send($container->get('bns.group_manager')->setGroup($blogGroup)->getUsersByPermissionUniqueName('BLOG_ADMINISTRATION', true), new BlogCommentPendingValidationNotification($container, $this->getId(), $blogGroup->getId()));
		}

		return $affectedRows;
	}

	/**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array $row The row returned by PDOStatement->fetch(PDO::FETCH_NUM)
     * @param int $startcol 0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
	 *
     * @return int             next starting column
	 *
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false)
    {
		$startcol = parent::hydrate($row, $startcol, $rehydrate);
		$this->commentBeforeSave = clone $this;

		return $startcol;
    }
}
