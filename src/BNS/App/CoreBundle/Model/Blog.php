<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlog;
use RuntimeException;

/**
 * Skeleton subclass for representing a row from the 'blog' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class Blog extends BaseBlog
{
	const PERMISSION_BLOG_ADMINISTRATION	= 'BLOG_ADMINISTRATION';
	const PERMISSION_BLOG_ACCESS_BACK		= 'BLOG_ACCESS_BACK';
	const PERMISSION_BLOG_ACTIVATION		= 'BLOG_ACTIVATION';
	const PERMISSION_BLOG_ACCESS			= 'BLOG_ACCESS';
	
	/**
	 * @var array<BlogArticle> 
	 */
	private $starArticles;
	
	/**
	 * @var array<BlogArticle> 
	 */
	private $normalArticles;
	
	/**
	 * @var array
	 */
	private $archives;
	
	/**
	 * @var array<BlogCategory> 
	 */
	private $sortedCategories;
	
	/**
	 * @var array<Integer, BlogCategory>
	 */
	private $parentCategoriesFromChild;
	
	/**
	 * @var array<User> 
	 */
	private $authors;
	
	/**
	 * @return array 
	 */
	public function getArchives()
	{
		if (!isset($this->archives)) {
			$articles = $this->getBlogArticles();
			$this->archives = array();
			
			foreach ($articles as $article) {
				if ($article->isPublished()) {
					$month	= date('m', $article->getPublishedAt()->getTimestamp());
					$year	= date('Y', $article->getPublishedAt()->getTimestamp());
					$label	= $article->getPublishedAt()->getMonth() . ' ' . $article->getPublishedAt()->getYear();
					
					if (isset($this->archives[$label])) {
						$this->archives[$label] = array(
							'month' => $month,
							'year'	=> $year,
							'count'	=> $this->archives[$label]['count'] + 1
						);
					}
					else {
						$this->archives[$label] = array(
							'month' => $month,
							'year'	=> $year,
							'count'	=> 1
						);
					}
				}
			}
		}
		
		return $this->archives;
	}
	
	public function getSortedBlogCategories()
	{
		if (!isset($this->sortedCategories)) {
			$this->sortedCategories = array();
			
			// Récupération des parents en cachant la catégorie principale (Root Category), puis des enfants
			foreach ($this->getBlogCategories() as $category) {
				if ($category->getLevel() == 1 && !$category->isRoot()) {
					$this->sortedCategories[$category->getId()] = array(
						'parent'	=> $category,
						'children'	=> $category->getChildrenFromCollection($this->getBlogCategories())
					);
				}
			}
		}
		
		return $this->sortedCategories;
	}
	
	/**
	 * @param array<BlogArticle> $articles 
	 */
	public function replaceBlogArticles($articles)
	{
		$this->collBlogArticles = $articles;
	}
	
	/**
	 * @param BlogCategory $childCategory
	 * 
	 * @return BlogCategory The $childCategory's parent
	 * 
	 * @throws \RuntimeException 
	 */
	public function getParentCategoryFromChild(BlogCategory $childCategory)
	{
		if (!isset($this->parentCategoriesFromChild)) {
			$this->parentCategoriesFromChild = array();
			
			foreach ($this->getSortedBlogCategories() as $category) {
				foreach ($category['children'] as $child) {
					$this->parentCategoriesFromChild[$child->getId()] = $category['parent'];
				}
			}
		}
		
		if (!isset($this->parentCategoriesFromChild[$childCategory->getId()])) {
			throw new RuntimeException('Category id : ' . $childCategory->getId() . ' is not found !');
		}
		
		return $this->parentCategoriesFromChild[$childCategory->getId()];
	}
	
	/**
	 * @return array<User> 
	 */
	public function getAuthors()
	{
		if (!isset($this->authors)) {
			$this->authors = UserQuery::create()
				->join('BlogArticle')
				->joinWith('Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->add(BlogArticlePeer::BLOG_ID, $this->getId())
				->addAnd(BlogArticlePeer::STATUS, BlogArticlePeer::STATUS_PUBLISHED_INTEGER)
				->addAnd(BlogArticlePeer::PUBLISHED_AT, time(), \Criteria::LESS_EQUAL)
				->addGroupByColumn(UserPeer::ID)
				->addAscendingOrderByColumn(UserPeer::LAST_NAME)
			->find();
		}
		
		return $this->authors;
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return boolean 
	 */
	public function isCommentModerate()
	{
		return $this->getIsCommentModerate();
	}
	
	/**
	 * Switch is comment moderate
	 */
	public function switchIsCommentModerate()
	{
		if ($this->getIsCommentModerate()) {
			$this->setIsCommentModerate(false);
		}
		else {
			$this->setIsCommentModerate(true);
		}
	}
	
	/**
	 * @return string 
	 */
	public function __toString()
	{
		return '#' . $this->getId() . ': ' . $this->getTitle();
	}
}