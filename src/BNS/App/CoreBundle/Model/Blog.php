<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlog;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\YerbookBundle\Model\YerbookSelectionQuery;
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
	 * @var array<BlogCategory>
	 */
	private $usedCategories;
	
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
            $categories = BlogCategoryQuery::create()
                ->filterByBlogId($this->getId())
                ->orderBy('left',\Criteria::ASC)
                ->find();
            $criteria = new \Criteria();
            $criteria->addAscendingOrderByColumn(BlogCategoryPeer::LEFT);
			foreach ($categories as $category) {
				if ($category->getLevel() == 1 && !$category->isRoot()) {
					$this->sortedCategories[$category->getId()] = array(
						'parent'	=> $category,
						'children'	=> $category->getChildrenFromCollection($this->getBlogCategories($criteria), true)
					);
				}
			}
		}
		return $this->sortedCategories;
	}

    /**
     * Renvoie la catégorie parente de tous pour le blog, qui a été créée par défaut
     *
     * @param \PropelPDO $con
     * @return BlogCategory
     */
    public function getRootCategory(\PropelPDO $con = null)
    {
        return BlogCategoryQuery::create()->findRoot($this->getId(), $con);
    }

    public function getOrderedCategories()
    {
        return $this->getRootCategory()->getChildren();
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
			return false;
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
				->useBlogArticleQuery()
                    ->filterByBlog($this)
                ->endUse()
				->joinWith('Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
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
	 * Return only used categories in the blog
	 * Associated with keys :
	 *  - parent (BlogCategory)
	 *  - count (int)
	 *  - children (array)
	 *    - child (BlogCategory)
     *    - count (int)
	 * 
	 * @return array<BlogCategory>
	 */
	public function getUsedBlogCategories()
	{
		if (!isset($this->usedCategories)) {
			$categories = $this->getBlogCategories();
			$articleCategories = BlogArticleCategoryQuery::create('bac')
				->join('bac.BlogArticle ba')
				->where('ba.Status = ?', BlogArticlePeer::STATUS_PUBLISHED)
				->where('ba.PublishedAt <= ?', time())
				->where('bac.CategoryId IN ?', $categories->getPrimaryKeys())
				->setFormatter(\ModelCriteria::FORMAT_ARRAY)
			->find();
			
			$sortedCategories = $this->getSortedBlogCategories();
			$articleCategoriesIds = array();
			
			// Count process
			foreach ($articleCategories as $articleCategory) {
				if (!isset($articleCategoriesIds[$articleCategory['CategoryId']])) {
					$articleCategoriesIds[$articleCategory['CategoryId']] = 0;
				}

				++$articleCategoriesIds[$articleCategory['CategoryId']];
			}

			$this->usedCategories = array();
			foreach ($sortedCategories as $category) {
				// Parent category process
				if (isset($articleCategoriesIds[$category['parent']->getId()])) {
					$this->usedCategories[$category['parent']->getId()] = array(
						'parent'	=> $category['parent'],
						'children'	=> array(),
						'count'		=> $articleCategoriesIds[$category['parent']->getId()]
					);
				}

				// Children categories process
				foreach ($category['children'] as $subCategory) {
					if (isset($articleCategoriesIds[$subCategory->getId()])) {
						if (!isset($articleCategoriesIds[$category['parent']->getId()])) {
							$this->usedCategories[$category['parent']->getId()] = array(
								'parent'	=> $category['parent'],
								'children'	=> array(array(
									'child' => $subCategory,
									'count'	=> $articleCategoriesIds[$subCategory->getId()]
								)),
								'count'		=> 1
							);
						}
						else {
							++$this->usedCategories[$category['parent']->getId()]['count'];
							$this->usedCategories[$category['parent']->getId()]['children'][] = array(
								'child' => $subCategory,
								'count'	=> $articleCategoriesIds[$subCategory->getId()]
							);
						}
					}
				}
			}
		}
		
		return $this->usedCategories;
	}
	
	/**
	 * @return string 
	 */
	public function __toString()
	{
		return '#' . $this->getId() . ': ' . $this->getTitle();
	}


}
