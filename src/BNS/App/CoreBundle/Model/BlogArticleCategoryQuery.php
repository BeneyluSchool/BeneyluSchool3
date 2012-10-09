<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogArticleCategoryQuery;

use Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'blog_article_category' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogArticleCategoryQuery extends BaseBlogArticleCategoryQuery
{
	/**
	 * @param array[BlogArticle] $articles
	 * 
	 * @return array[BlogArticle] 
	 */
	public static function injectCategoriesFromArticles($articles)
	{
		$articleIds = array();
		foreach ($articles as $article) {
			$articleIds[] = $article->getId();
		}
		
		$categories = BlogArticleCategoryQuery::create()
			->joinWith('BlogCategory')
			->add(BlogArticleCategoryPeer::ARTICLE_ID, $articleIds, Criteria::IN)
		->find();
		
		foreach ($articles as $article) {
			$article->initBlogArticleCategories();
			
			foreach ($categories as $category) {
				if ($article->getId() == $category->getArticleId()) {
					$article->addBlogArticleCategory($category);
				}
			}
		}
		
		return $articles;
	}
}