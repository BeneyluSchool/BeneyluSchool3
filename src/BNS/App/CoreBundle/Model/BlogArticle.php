<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseBlogArticle;
use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\CorrectionBundle\Model\CorrectionInterface;
use BNS\App\CorrectionBundle\Model\CorrectionTrait;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogArticleFinishedNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogArticlePendingCorrectionNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogArticleProgrammedNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogArticlePublishedAuthorNotification;
use BNS\App\NotificationBundle\Notification\BlogBundle\BlogArticlePublishedEveryoneNotification;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogArticle extends BaseBlogArticle implements AutosaveInterface, CorrectionInterface
{
    use CorrectionTrait;

	/**
	 * @var array<String, array<Integer>> ALLOWED_NEW_STATUSES Si le numéro du statut se trouve dans le tableau,
	 * alors l'article est autorisé à changer de statut
	 *
	 * 0: DRAFT
	 * 1: PUBLISHED
	 * 2: FINISHED
	 * 3: WAITING_FOR_CORRECTION
	 * programmed: PROGRAMMED
	 */
	public static $ALLOWED_NEW_STATUSES = array(
		BlogArticlePeer::STATUS_DRAFT => array(
			1, 2, 3, 'programmed'
		),
		BlogArticlePeer::STATUS_FINISHED => array(
			1, 3, 'programmed'
		),
		BlogArticlePeer::STATUS_PROGRAMMED_PUBLISH => array(
			1, 2, 'programmed'
		),
		BlogArticlePeer::STATUS_PUBLISHED => array(
			1 /*programmed*/, 2
		),
		BlogArticlePeer::STATUS_WAITING_FOR_CORRECTION => array(
			1, 2, 'programmed'
		)
	);

	/**
	 * @var array<Integer, BlogCategory>
	 */
	private $categories;

	/**
	 * @var array<Integer>
	 */
	private $categoriesListId;

	/**
	 * @var BlogArticle
	 */
	private $articleBeforeSave;

    public $yerbookHeight;

	/**
	 * Simple shortcut
	 *
	 * @return boolean True if is star, false otherwise
	 */
	public function isStar()
	{
		return $this->getIsStar();
	}

	/**
	 * Simple shortcut
	 *
	 * @return User
	 */
	public function getAuthor()
	{
		return $this->getUser();
	}

    public function flushCategories()
    {
        foreach($this->getBlogArticleCategories() as $link)
        {
            $link->delete();
        }
    }

	/**
     * @param interger $height
     */
    public function setYerbookHeight($height)
    {
        $this->yerbookHeight = $height;
    }

    /**
     * @return INT
     */
    public function getYerbookHeight()
    {
        return $this->yerbookHeight;
    }

    /**
	 * Inverse the comments order
	 *
	 * @param type $limit
	 *
	 * @return array<BlogArticleComment>
	 */
	public function getBlogArticleCommentsInverse($limit = 10, $blogId = null)
	{
        $criteria = new \Criteria();
        $criteria->addDescendingOrderByColumn(BlogArticleCommentPeer::DATE);
        $criteria->setLimit($limit);
        if($blogId != null)
        {
            $criteria->add(BlogArticleCommentPeer::BLOG_ID, $blogId, \Criteria::EQUAL);
        }

		return $this->getBlogArticleComments($criteria)->getArrayCopy('id');

	}


    /**
     * @param int $limit
     * @param int|null $blogId
     * @param bool|false $onlyValidated
     * @param int|null $userId
     * @param bool|false $hasAdminRight
     * @return array
     * @throws \PropelException
     */
    public function getBlogArticleCommentsFiltered($limit = 10, $blogId = null, $onlyValidated = false, $userId = null, $hasAdminRight = false)
    {
        /** @var BlogArticleCommentQuery $query */
        $query = BlogArticleCommentQuery::create('BlogArticleComment')
            ->orderByDate(\Criteria::DESC)
            ->setLimit($limit);

        if ($blogId) {
            $query->filterByBlogId($blogId, \Criteria::EQUAL);
        }

        if ($onlyValidated) {
            $query->filterByStatus(BlogArticleCommentPeer::STATUS_VALIDATED);
        } else if ($hasAdminRight) {
            $query->filterByStatus([BlogArticleCommentPeer::STATUS_VALIDATED, BlogArticleCommentPeer::STATUS_PENDING_VALIDATION]);
        } else if ($userId) {

            $query->condition('pending', 'BlogArticleComment.Status = ?', BlogArticleCommentPeer::STATUS_PENDING_VALIDATION);
            $query->condition('author', 'BlogArticleComment.AuthorId = ?', $userId);
            $query->combine(['pending', 'author'], \Criteria::LOGICAL_AND, 'pendingAuthor');
            $query->condition('validated', 'BlogArticleComment.Status = ?', BlogArticleCommentPeer::STATUS_VALIDATED);
            $query->combine(['pendingAuthor', 'validated'], \Criteria::LOGICAL_OR);
        } else {
            $query->filterByStatus(BlogArticleCommentPeer::STATUS_VALIDATED);
        }

        return $this->getBlogArticleComments($query)->getArrayCopy('id');
    }

    public function getSortedCategories(Blog $blog = null)
    {
        $query = BlogCategoryQuery::create()->orderByBlogId()->orderByLeft();
        if ($blog) {
            $query->filterByBlog($blog);
        }

        return $this->getBlogCategories($query);
    }

	/**
     * @deprecated do not use, look at self::getSortedCategories()
	 * @return array<BlogCategory>
	 */
	public function getSortedBlogCategories()
	{
		$sortedCategories = array();
		$categories = $this->getBlogCategories();

		// Get all parents if parent category had not been selected
        foreach ($categories as $category) {
            if ($category->getLevel() == 2) {
                $parent = $category->getParent();
                if($parent)
                {
                    $categories[$parent->getId()] = $parent;
                }
            }
        }

		foreach ($categories as $category) {
			if ($category->getLevel() == 1 && !$category->isRoot()) {
				$sortedCategories[] = $category;
				$category->getChildrenFromCollection($categories, true);
			}
		}

		return $sortedCategories;
	}

	/**
	 * @param BlogCategory $category
	 *
	 * @return boolean
	 */
	public function hasCategory(BlogCategory $category, $categoryIds = null)
	{
		if (null == $categoryIds) {
			$categories = $this->getBlogCategories();
		}
		else {
			$categories = array_flip(explode(',', $categoryIds));
		}

		return isset($categories[$category->getId()]);
	}

	/**
	 * @return boolean True if article has status to PUBLISHED, false otherwise
	 */
	public function isPublished()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_PUBLISHED && null != $this->getPublishedAt() && $this->getPublishedAt()->getTimestamp() <= time();
	}

	/**
	 * @return boolean
	 */
	public function isDraft()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_DRAFT;
	}

	/**
	 * @return boolean
	 */
	public function isFinished()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_FINISHED;
	}

	/**
	 * @return boolean
	 */
	public function isWaitingForCorrection()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_WAITING_FOR_CORRECTION;
	}

	/**
	 * @return boolean
	 */
	public function isProgrammed()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_PUBLISHED && null != $this->getPublishedAt() && $this->getPublishedAt()->getTimestamp() > time();
	}

	/**
	 * Simple shortcut
	 *
	 * @param $user User
	 */
	public function setAuthor(User $user)
	{
		$this->setUser($user);
	}

	/**
	 * @return array<Integer>
	 */
	public function getCategoriesListId($toArray = false)
	{
		if (!isset($this->categoriesListId)) {
			$categories = $this->getBlogCategories();
			$this->categoriesListId = array();

			foreach ($categories as $category) {
				if (!in_array($category->getId(), $this->categoriesListId)) {
					$this->categoriesListId[] = $category->getId();
				}
			}
		}

		return $toArray ? $this->categoriesListId : implode(',', $this->categoriesListId);
	}

	/**
	 * @param string $categoriesListId
	 */
	public function setCategoriesListId($categoriesListId)
	{
		if (null != $categoriesListId) {
			$categoriesId = preg_split('#,#', $categoriesListId);
			$this->categoriesListId = array();

			foreach ($categoriesId as $category) {
				if (!in_array($category, $this->categoriesListId)) {
					$this->categoriesListId[] = $category;
				}
			}
		}
		else {
			$this->categoriesListId = array();
		}
	}

	/**
	 * @return string The title
	 */
	public function __toString()
	{
		if($this->isNew())
			return '#new';
		return $this->getTitle();
	}

	/**
	 * @param array $objects
	 *
	 * @return int The object's primary key
	 *
	 * @throws AccessDeniedHttpException
	 */
	public function autosave(array $objects)
	{
        $container = BNSAccess::getContainer();
        $rightManager = $container->get('bns.right_manager');
		// Check rights
		if (!$rightManager->hasRight(Blog::PERMISSION_BLOG_ACCESS_BACK)) {
			throw new AccessDeniedHttpException('You can NOT access to this page');
		}
        $allowedBlogIds = BlogQuery::create()
            ->filterByGroupId($rightManager->getGroupIdsWherePermission(Blog::PERMISSION_BLOG_ACCESS_BACK))
            ->select(['Id'])
            ->find()
            ->getArrayCopy()
        ;

        // check if has acces to requested blog
        if (!in_array($objects['blog_id'], $allowedBlogIds)) {
            throw new AccessDeniedHttpException('You can NOT access to this page');
        }

        if (isset($objects['blog_ids'])) {
            $blogIds = $objects['blog_ids'];
            if (is_array($blogIds)) {
                $blogIds = array_intersect($blogIds, $allowedBlogIds);
                if (!count($blogIds)) {
                    throw new AccessDeniedHttpException('You can NOT access to this page');
                }
            } else {
                if ($blogIds && !in_array($blogIds, $allowedBlogIds)) {
                    throw new AccessDeniedHttpException('You can NOT access to this page');
                }
            }
        } else {
            $blogIds = $objects['blog_id'];
        }



		if (!isset($objects['categories_list_id'])) {
			$objects['categories_list_id'] = null;
		}

		if (null == $objects['categories_list_id']) {
			$objects['categories_list_id'] = '';
			$categoriesId = array();
		}
		else {
			$categoriesId = explode(',', $objects['categories_list_id']);
		}

		$categoriesCount = count($categoriesId);
		if ($categoriesCount > 0) {
			$count = BlogCategoryQuery::create('bc')
				->where('bc.Id IN ?', $categoriesId)
			->count();

			// L'utilisateur a modifié manuellement la balise car une ou plusieurs catégories ne sont pas en base : erreur
			if ($count < $categoriesCount) {
				throw new \RuntimeException('What did you try ?');
			}
		}

		// New object : save into database and return his new primary key
		if ($this->isNew()) {
			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setAuthor(BNSAccess::getUser());
			$this->setUpdater(BNSAccess::getUser());
			$this->setUpdatedAt(time());
			$this->setCreatedAt(time());
            $this->setBlogReferenceId($objects['blog_id']);
			$this->setBlogs(BlogQuery::create()->filterById($blogIds)->find());
			$this->setStatus(BlogArticlePeer::STATUS_DRAFT_INTEGER);
            $this->save();

			// Add categories process
			if ($categoriesCount > 0) {
				foreach ($categoriesId as $categoryId) {
					$category = new BlogArticleCategory();
					$category->setArticleId($this->getId());
					$category->setCategoryId($categoryId);
					$category->save();
				}
			}
            //Stat Action
            $container->get('stat.blog')->newArticle();
		}
		else {
			// Check rights
			if (($this->isPublished() || $this->isProgrammed()) && !$container->get('bns.right_manager')->hasRight(Blog::PERMISSION_BLOG_ADMINISTRATION)) {
				throw new AccessDeniedHttpException('You can NOT access to this page');
			}

			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setUpdater(BNSAccess::getUser());
			$this->setUpdatedAt(time());
			$this->setStatus(BlogArticlePeer::STATUS_DRAFT_INTEGER);
            $this->setBlogs(BlogQuery::create()->filterById($blogIds)->find());
			$this->save();

			// Add/delete categories process
			$categories = $this->getBlogCategories();
			$categoryIds = array();
			$this->setCategoriesListId($objects['categories_list_id']);

			foreach ($categories as $key => $value) {
				$categoryIds[] = $key;
			}

			$categoriesToDelete	= array_diff($categoryIds, $this->getCategoriesListId(true));
			$categoriesToAdd	= array_diff($this->getCategoriesListId(true), $categoryIds);

			BlogArticleCategoryQuery::create()
				->filterByArticleId($this->getId())
				->filterByCategoryId($categoriesToDelete)
			->delete();

			foreach ($categoriesToAdd as $categoryId) {
				$category = new BlogArticleCategory();
				$category->setArticleId($this->getId());
				$category->setCategoryId($categoryId);
				$category->save();
			}
		}

		return $this->getPrimaryKey();
	}

	/**
	 * @return string 180 characters content
	 */
	public function getShortContent()
	{
		return StringUtil::substrws($this->getContent());
	}

	/**
	 * Simple shortcut
	 *
	 * @return boolean
	 */
	public function isCommentAllowed()
	{
		return $this->getIsCommentAllowed();
	}

	/**
	 * @param \PropelPDO $con
	 *
	 * @return int
	 */
	public function save(\PropelPDO $con = null, $skipNotification = false, $skipStats = false)
	{
        $isNew = $this->isNew();
		$affectedRows = parent::save($con);

		// Si l'article est nouveau, aucun ancien article enregistré, on le créer à la volée
		if (!isset($this->articleBeforeSave)) {
			$this->articleBeforeSave = new BlogArticle();
		}
		// Set the new articleBeforeSave to prevent double notifications
		$articleBeforeSave = $this->articleBeforeSave;
        $this->articleBeforeSave = clone $this;

		$container = BNSAccess::getContainer();
        if ($isNew && $container && !$skipStats) {
            //Stat Action
            $container->get('stat.blog')->newArticle();
        }
		if ($skipNotification || null == $container) {
			// If $container == null, this method is called by CLI
			return $affectedRows;
		}

		// Notifications process
		$rightManager = $container->get('bns.right_manager');
		$classroomManager = $container->get('bns.classroom_manager');
		$classroomManager->setGroup($rightManager->getCurrentGroup());

        $blogs = $this->getBlogs();
        $usersInGroupsOnce = [];
		foreach ($blogs as $blog) {
            $blogGroups [] = $blog->getGroup();
        }

        if ($this->isNew()) {
		    // TODO remove this, it seems impossible to have the state isNew after the save
			// Article terminé PAR élève POUR enseignants
			if ($this->isFinished() && !$rightManager->hasRight('BLOG_ADMINISTRATION')) {
                foreach ($blogGroups as $blogGroup) {
                    $usersInGroups = [];
                    $notEmpty = false;
                    $groupManager = $container->get('bns.group_manager');
                    $groupManager->setGroup($blogGroup);
                    foreach ($groupManager->getUsersByPermissionUniqueName('BLOG_ADMINISTRATION', true) as $notifiedUser) {
                        if (!in_array($notifiedUser->getId(), $usersInGroupsOnce)){
                            $usersInGroupsOnce [] = $notifiedUser->getId();
                            $usersInGroups [] = $notifiedUser;
                            $notEmpty = true;
                        }
                    }
                    if ($notEmpty ) {
                        $container->get('notification_manager')->send($usersInGroups, new BlogArticleFinishedNotification($container, $this->getId(), $blogGroup->getId()), BNSAccess::getUser());
                    }
                }
            }
			// Article publié PAR enseignant POUR élèves
			elseif ($rightManager->hasRight('BLOG_ADMINISTRATION') && $this->isPublished()) {
                foreach ($blogGroups as $blogGroup) {
                    $usersInGroups = [];
                    $notEmpty = false;
                    $groupManager = $container->get('bns.group_manager');
                    $groupManager->setGroup($blogGroup);
                    foreach ($groupManager->getUsersByPermissionUniqueName('BLOG_ACCESS', true) as $notifiedUser) {
                        if (!in_array($notifiedUser->getId(), $usersInGroupsOnce)){
                            $usersInGroupsOnce [] = $notifiedUser->getId();
                            $usersInGroups [] = $notifiedUser;
                            $notEmpty = true;
                        }
                    }
                    if ($notEmpty) {
                       $container->get('notification_manager')->send($usersInGroups, new BlogArticlePublishedEveryoneNotification($container, $this->getId(), $blogGroup->getId()), BNSAccess::getUser());
                    }

                }
			}

		}
		else {
			// Article terminé PAR élève POUR enseignants
			if (!$rightManager->hasRight('BLOG_ADMINISTRATION') &&
				$this->isFinished() &&
				!$articleBeforeSave->isFinished())
			{
                foreach ($blogGroups as $blogGroup) {
                    $usersInGroups = [];
                    $notEmpty = false;
                    $groupManager = $container->get('bns.group_manager');
                    $groupManager->setGroup($blogGroup);
                    foreach ( $groupManager->getUsersByPermissionUniqueName('BLOG_ADMINISTRATION', true) as $notifiedUser) {
                        if (!in_array($notifiedUser->getId(), $usersInGroupsOnce)){
                            $usersInGroupsOnce [] = $notifiedUser->getId();
                            $usersInGroups [] = $notifiedUser;
                            $notEmpty = true;
                        }
                    }
                    if ($notEmpty) {
                        $container->get('notification_manager')->send($usersInGroups, new BlogArticleFinishedNotification($container, $this->getId(), $blogGroup->getId()), BNSAccess::getUser());
                    }
                }
            }
			// Article publié PAR enseignant POUR groupe (élèves) & auteur (élève)
			elseif ($rightManager->hasRight('BLOG_ADMINISTRATION') &&
					$this->isPublished() &&
					!$articleBeforeSave->isPublished())
			{
                foreach ($blogGroups as $blogGroup) {
                    $usersInGroups = [];
                    $notEmpty = false;
                    $groupManager = $container->get('bns.group_manager');
                    $groupManager->setGroup($blogGroup);
                    foreach ( $groupManager->getUsersByPermissionUniqueName('BLOG_ACCESS', true) as $notifiedUser) {
                        if (!in_array($notifiedUser->getId(), $usersInGroupsOnce)){
                            $usersInGroupsOnce [] = $notifiedUser->getId();
                            $usersInGroups [] = $notifiedUser;
                            $notEmpty = true;
                        }
                    }
                    if ($notEmpty) {
                        // Élèves, auteur exclu
                        $container->get('notification_manager')->send($usersInGroups, new BlogArticlePublishedEveryoneNotification($container, $this->getId(), $blogGroup->getId()), array(
                            BNSAccess::getUser(),
                            $this->getAuthor()
                        ));
                    }
                }
				// Auteur
				if ($this->getAuthor()->getId() != BNSAccess::getUser()->getId()) {
					$container->get('notification_manager')->send($this->getAuthor(), new BlogArticlePublishedAuthorNotification($container, $this->getId(), $rightManager->getCurrentGroup()->getId()), BNSAccess::getUser());
				}
			}
			// Article à corriger PAR enseignant POUR auteur (élève)
			elseif ($rightManager->hasRight('BLOG_ADMINISTRATION') &&
					$this->isWaitingForCorrection() &&
					!$articleBeforeSave->isWaitingForCorrection())
			{
				$container->get('notification_manager')->send($this->getAuthor(), new BlogArticlePendingCorrectionNotification($container, $this->getId(), $rightManager->getCurrentGroup()->getId()), BNSAccess::getUser());
			}
			// Article programmé PAR enseignant POUR groupe (élève)
			elseif ($rightManager->hasRight('BLOG_ADMINISTRATION') &&
					$this->isProgrammed() &&
					!$articleBeforeSave->isProgrammed())
			{
                foreach ($blogGroups as $blogGroup) {
                    $usersInGroups = [];
                    $notEmpty = false;
                    $groupManager = $container->get('bns.group_manager');
                    $groupManager->setGroup($blogGroup);
                    foreach ( $groupManager->getUsersByPermissionUniqueName('BLOG_ACCESS', true) as $notifiedUser) {
                        if (!in_array($notifiedUser->getId(), $usersInGroupsOnce)){
                            $usersInGroupsOnce [] = $notifiedUser->getId();
                            $usersInGroups [] = $notifiedUser;
                            $notEmpty = true;
                        }
                    }
                    if ($notEmpty) {
                        $container->get('notification_manager')->send($usersInGroups, new BlogArticleProgrammedNotification($container, $this->getId(), $blogGroup->getId()), BNSAccess::getUser());
                    }
                }
            }
			return $affectedRows;
		}
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
		$this->articleBeforeSave = clone $this;

		return $startcol;
    }

    public function getResourceAttachments()
    {
        if($this->isNew() && isset($this->attachments))
        {
            return $this->attachments;
        }else{
            return parent::getResourceAttachments();
        }
    }

    /**
     * @inheritDoc
     */
    public static function getCorrectionRightName()
    {
        return 'BLOG_CORRECTION';
    }

    public function createSlug()
    {
        if (!$this->isNew()) {
            $key = $this->getId();
        } else {
            $key = 'key-' . rand(999999999, min(9999999999, PHP_INT_MAX));
        }

        return 'blog-article-' . $key;
    }

}
