<?php

namespace BNS\App\BlogBundle\Form\Model;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\BlogArticleCategoryQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\ResourceBundle\BNSResourceManager;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ExecutionContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BlogArticleFormModel
{
	public $categories_list_id;
	public $title;
	public $content;
	public $status;
	public $programmation_day;
	public $programmation_time;
	
	private $article;
	
	public function __construct(BlogArticle $article = null)
	{
		if (null == $article) {
			$this->article = new BlogArticle();
			
			return;
		}
		
		$this->article = $article;
		$this->categories_list_id = $article->getCategoriesListId();
		$this->title = $article->getTitle();
		$this->content = $article->getContent();
		
		$this->status = $article->getStatus();
		if ($article->isProgrammed()) {
			$this->status = 'PROGRAMMED';
		}
		
		if ($article->isPublished() || $article->isProgrammed()) {
			$this->programmation_day = $article->getPublishedAt();
			$this->programmation_time = $article->getPublishedAt()->getTime();
		}
	}
	
	public function preSave()
	{
		$this->article->setCategoriesListId($this->categories_list_id);
		$this->article->setTitle($this->title);
		$this->article->setContent($this->content);
	}
			
	public function save(BNSRightManager $rightManager, User $user, BNSResourceManager $resourceManager, Request $request, Blog $blog = null)
	{
		$this->preSave();
		
		// Process for new article only
		if ($this->article->isNew()) {
			$this->article->setCreatedAt(time());
			
			if (null == $blog) {
				throw new \RuntimeException('The blog can NOT be null !');
			}
			
			$this->article->setBlogId($blog->getId());
			$this->article->setAuthorId($user->getId());
		}
		
		$this->article->setUpdatedAt(time());
		
		// Add/delete categories process
		$categories = $this->article->getBlogCategories();
		$categoryIds = array();

		foreach ($categories as $key => $value) {
			$categoryIds[] = $key;
		}

		$categoriesToDelete	= array_diff($categoryIds, $this->article->getCategoriesListId(true));
		$categoriesToAdd	= array_diff($this->article->getCategoriesListId(true), $categoryIds);

		BlogArticleCategoryQuery::create('ac')
			->where('ac.CategoryId IN ?', $categoriesToDelete)
		->delete();

		foreach ($categoriesToAdd as $categoryId) {
			$category = new BlogArticleCategory();
			$category->setArticleId($this->article->getId());
			$category->setCategoryId($categoryId);
			$category->save();
		}
		
		if ($rightManager->hasRight(Blog::PERMISSION_BLOG_ADMINISTRATION)) {
			if ($this->status == BlogArticlePeer::STATUS_PUBLISHED || $this->status == 'PROGRAMMED') {
				$this->article->setStatus(BlogArticlePeer::STATUS_PUBLISHED);
				
				if ($this->status == 'PROGRAMMED') {
					$this->article->setPublishedAt(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time);
				}
				else {
					$this->article->setPublishedAt(time());
				}
			}
			else {
				$this->article->setStatus($this->status);
				$this->article->setPublishedAt(null);
			}
		}
		else {
			$this->article->setStatus(BlogArticlePeer::STATUS_FINISHED);
			$this->article->setPublishedAt(null);
		}
		
		$this->article->save();
		
		// Attached files process
		$resourceManager->saveAttachments($this->article, $request);
	}
	
	public function getArticle()
	{
		return $this->article;
	}
	
	/**
	 * Constraint validation
	 */
	public function isStatusExists(ExecutionContext $context)
	{
		$statuses = BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS);
		$statuses[] = 'PROGRAMMED'; // custom status
		
		if (!in_array($this->status, $statuses)) {
			$context->addViolationAtSubPath('status', "Le statut de l'article n'est pas correct, veuillez réessayer", array(), null);
		}
	}
	
	/**
	 * Constraint validation
	 */
	public function isProgrammationValid(ExecutionContext $context)
	{
		if ($this->status == 'PROGRAMMED') {
			if (!$this->programmation_day instanceof \DateTime) {
				$context->addViolationAtSubPath('programmation_day', "La date est invalide", array(), null);
			}
			elseif (!preg_match('/^((([0]?[1-9]|1[0-2])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?( )?(AM|am|aM|Am|PM|pm|pM|Pm))|(([0]?[0-9]|1[0-9]|2[0-3])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?))$/', $this->programmation_time)) {
				$context->addViolationAtSubPath('programmation_time', "L'heure est invalide", array(), null);
			}
			elseif (date('Y-m-d', $this->programmation_day->getTimestamp()) < date('Y-m-d', time()) || strtotime(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time) < time()) {
				$context->addViolationAtSubPath('programmation_day', "La date et l'heure doivent obligatoirement être dans le futur", array(), null);
			}
		}
	}
}