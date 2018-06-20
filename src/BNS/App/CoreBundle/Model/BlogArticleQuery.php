<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogArticleQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

use Criteria;

/**
 * Skeleton subclass for performing query and update operations on the 'blog_article' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class BlogArticleQuery extends BaseBlogArticleQuery
{
	/**
	 * @param Blog $blog
	 * @param null $pager
	 * @param null|int $page
	 * @param int $limit
	 *
	 * @return array[BlogArticle]
	 */
	public static function getArticlesFromBlog(Blog $blog, &$pager = null, $page = null, $filters = null, $limit = 30)
	{
		$query = self::create()
            ->join('BlogArticleBlog')
            ->join('BlogArticleBlog.Blog')
            ->add(BlogPeer::ID, $blog->getId())
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->addDescendingOrderByColumn(BlogArticlePeer::CREATED_AT)
            ->addGroupByColumn(BlogArticlePeer::ID);

		if (null != $filters) {
			if (isset($filters['filters']) && count($filters['filters']) > 0) {
				foreach ($filters['filters'] as $filter) {
					if ('programmed' == $filter) {
						$query->add(BlogArticlePeer::PUBLISHED_AT, time(), Criteria::GREATER_THAN);
					}
					else if (BlogArticlePeer::STATUS_PUBLISHED_INTEGER == $filter) {
						$query->add(BlogArticlePeer::PUBLISHED_AT, time(), Criteria::LESS_EQUAL);
					}
					else {
                        $filtersCriteria[] = $filter;
					}
				}
			}

            if(isset($filtersCriteria) && is_array($filtersCriteria))
            {
                $query->add(BlogArticlePeer::STATUS, $filtersCriteria, \Criteria::IN);
            }

			if (isset($filters['categories']) && count($filters['categories']) > 0) {
				$query->join('BlogArticleCategory')
					  ->join('BlogArticleCategory.BlogCategory');

				foreach ($filters['categories'] as $i => $categoryId) {
					if ($i == 0) {
						$query->add(BlogCategoryPeer::ID, $categoryId); // TODO OR or AND where condition ?
					}
					else {
						$query->addOr(BlogCategoryPeer::ID, $categoryId); // TODO OR or AND where condition ?
					}
				}

				$query->addGroupByColumn(BlogArticlePeer::ID);
			}
		}



		// Show only own article if is pupil user
		if (!BNSAccess::getContainer()->get('bns.right_manager')->hasRight(Blog::PERMISSION_BLOG_ADMINISTRATION)) {
			$query->addAnd(BlogArticlePeer::AUTHOR_ID, BNSAccess::getUser()->getId());
		}

		if (null == $page) {
			$articles = $query->find();
		}
		else {
			$pager = $query->paginate($page, $limit);
			$articles = $pager->getResults();
		}

		return $articles;
	}

    public function isPublished()
    {
        $this->filterByStatus(BlogArticlePeer::STATUS_PUBLISHED, \Criteria::EQUAL);
        $this->filterByPublishedAt(time(), Criteria::LESS_EQUAL);

        return $this;
    }
}
