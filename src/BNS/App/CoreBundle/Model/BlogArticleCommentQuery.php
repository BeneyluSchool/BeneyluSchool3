<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogArticleCommentQuery;

use Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'blog_article_comment' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogArticleCommentQuery extends BaseBlogArticleCommentQuery
{
	public static function injectCommentFromArticles($articles, $limit = 5)
	{
		$articleIds = array();
		foreach ($articles as $article) {
			$articleIds[] = $article->getId();
		}

		$query = self::create()
			->joinWith('User')
			->add(BlogArticleCommentPeer::OBJECT_ID, $articleIds, Criteria::IN)
			->addDescendingOrderByColumn(BlogArticleCommentPeer::DATE)
		;

		if (null != $limit) {
			$query->setLimit($limit);
		}

		$comments = $query->find();

		foreach ($articles as $article) {
			$article->initBlogArticleComments();
			foreach ($comments as $comment) {
				if ($article->getId() == $comment->getObjectId()) {
					$article->addBlogArticleComment($comment);
				}
			}
		}

		return $articles;
	}

	/**
	 * Used by CommentBundle back
	 *
	 * @return BlogArticleCommentQuery
	 */
	public static function getBackComments($context)
	{
		return self::create('c')
            ->useBlogArticleQuery()
                ->useBlogArticleBlogQuery()
                    ->useBlogQuery()
                        ->filterByGroupId($context['id'])
                    ->endUse()
                ->endUse()
            ->endUse()
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
		;
	}

	/**
	 * @param array $context
	 *
	 * @return boolean
	 *
	 * @throws \InvalidArgumentException
	 */
	public static function isCommentModerate($context)
	{
		$blog = BlogQuery::create('b')
			->where('b.GroupId = ?', $context['id'])
		->findOne();

		if (null == $blog) {
			throw new \InvalidArgumentException('The blog with context id : ' . $context['id'] . ' is NOT found !');
		}

		return $blog->isCommentModerate();
	}

	/**
	 * Bypassing Symfony Twig DataCollector
	 *
	 * @return string
	 */
	public function __toString()
	{
		return 'BlogArticleCommentQuery';
	}
}
