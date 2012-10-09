<?php

namespace BNS\App\CoreBundle\Model;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BNS\App\CoreBundle\Model\om\BaseBlogArticle;
use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Utils\String;

/**
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogArticle extends BaseBlogArticle implements AutosaveInterface
{
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
	
	/**
	 * @param BlogCategory $category 
	 */
	public function addBlogCategory(BlogCategory $category)
	{
		$this->categories[] = $category;
	}
	
	/**
	 * Inverse the comments order
	 * 
	 * @return array<BlogArticleComment> 
	 */
	public function getBlogArticleCommentsPreviewInverse($limit = 10)
	{
		$comments = array();
		$coms = $this->getBlogArticleComments(BlogArticleCommentQuery::create()
			->joinWith('User')
			->addDescendingOrderByColumn(BlogArticleCommentPeer::DATE)
			->limit($limit)
		);
		$max = count($coms);
		
		for ($i=$max-1; $i>=0; $i--) {
			$comments[] = $coms[$i];
		}
		
		return $comments;
	}
	
	/**
	 * @return array<BlogCategory> 
	 */
	public function getBlogCategories()
	{
		if (!isset($this->categories)) {
			$this->categories = array();
			foreach ($this->getBlogArticleCategories() as $category) {
				$this->categories[$category->getBlogCategory()->getId()] = $category->getBlogCategory();
			}
		}
		
		return $this->categories;
	}
	
	/**
	 * @return array<BlogCategory> 
	 */
	public function getSortedBlogCategories()
	{
		$sortedCategories = array();
		$categories = $this->getBlogCategories();
		
		// Get all parents if parent category had not been selected
		foreach ($categories as $category) {
			if ($category->getLevel() == 2 && !isset($categories[$this->getBlog()->getParentCategoryFromChild($category)->getId()])) {
				$parent = $this->getBlog()->getParentCategoryFromChild($category);
				$categories[$parent->getId()] = $parent;
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
	public function hasCategory(BlogCategory $category)
	{
		$categories = $this->getBlogCategories();
		return isset($categories[$category->getId()]);
	}
	
	/**
	 * @return boolean True if article has status to PUBLISHED, false otherwise
	 */
	public function isPublished()
	{
		return $this->getStatus() == BlogArticlePeer::STATUS_PUBLISHED && $this->getPublishedAt()->getTimestamp() <= time();
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
				$this->categoriesListId[] = $category->getId();
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
			$this->categoriesListId = preg_split('#,#', $categoriesListId);
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
		return $this->getId() . ' - ' . $this->getTitle();
	}
	
	public function autosave(array $objects)
	{
		// Check rights
		if (!BNSAccess::getContainer()->get('bns.right_manager')->hasRight(Blog::PERMISSION_BLOG_ACCESS_BACK)) {
			throw new AccessDeniedHttpException('You can NOT access to this page');
		}
		
		// New object : save into database and return his new primary key
		if ($this->isNew()) {
			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setAuthor(BNSAccess::getUser());
			$this->setUpdatedAt(time());
			$this->setCreatedAt(time());
			$this->setBlogId($objects['blog_id']);
			$this->setStatus(BlogArticlePeer::STATUS_DRAFT_INTEGER);
			$this->save();
		}
		else {
			// Check rights
			if (($this->isPublished() || $this->isProgrammed()) && !BNSAccess::getContainer()->get('bns.right_manager')->hasRight(Blog::PERMISSION_BLOG_ADMINISTRATION)) {
				throw new AccessDeniedHttpException('You can NOT access to this page');
			}
			
			$this->setTitle($objects['title']);
			$this->setContent($objects['content']);
			$this->setUpdatedAt(time());
			$this->setStatus(BlogArticlePeer::STATUS_DRAFT_INTEGER);
			$this->save();
		}
		
		return $this->getPrimaryKey();
	}
	
	/**
	 * @return int
	 */
	public function getNbComments()
	{
		$nb = parent::getNbComments();
		if (null == $nb) {
			$nb = 0;
		}
		
		return $nb;
	}
	
	/**
	 * @return string 180 characters content
	 */
	public function getShortContent()
	{
		return String::substrws($this->getContent());
	}
}