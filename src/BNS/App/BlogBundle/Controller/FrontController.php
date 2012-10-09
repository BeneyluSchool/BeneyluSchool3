<?php

namespace BNS\App\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Criteria;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticleCategoryQuery;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogCategoryPeer;
use BNS\App\CoreBundle\Model\UserPeer;

class FrontController extends Controller
{
	/**
     * @Route("/", name="BNSAppBlogBundle_front")
	 * @Rights("BLOG_ACCESS")
     */
    public function indexAction()
    {
		return $this->renderBlog(1, $this->getBlog());
    }
	
	/**
     * @Route("/page/{page}", name="blog_articles_page")
	 * @Rights("BLOG_ACCESS")
     */
	public function getArticlesPageAction($page, $blog = null)
	{
		if (null == $blog) {
			$blog = $this->getBlog();
		}
		
		return $this->renderBlog($page, $blog);
	}
	
	/**
	 * @Route("/category/{categorySlug}", name="blog_articles_from_category")
	 * @Rights("BLOG_ACCESS")
	 */
	public function categoryAction($categorySlug)
	{
        return $this->categoryPageAction($categorySlug, 1);
	}
	
	/**
	 * @Route("/category/{categorySlug}/page/{page}", name="blog_articles_from_category_page")
	 * @Rights("BLOG_ACCESS")
	 */
	public function categoryPageAction($categorySlug, $page)
	{
		$blog = $this->getBlog();
        return $this->renderBlog($page, $blog, array(
			'BlogArticleQuery' => BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->join('BlogArticleCategory')
				->join('BlogArticleCategory.BlogCategory')
				->isPublished()
				->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
				->addAscendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
				->add(BlogArticlePeer::BLOG_ID, $blog->getId())
				->add(BlogCategoryPeer::SLUG, $categorySlug)
		), array('category_slug' => $categorySlug));
	}
	
	/**
	 * @Route("/archives/{month}/{year}", name="blog_articles_from_archives")
	 * @Rights("BLOG_ACCESS")
	 */
	public function archiveAction($month, $year)
	{
		return $this->archiveActionPage($month, $year, 1);
	}
	
	/**
	 * @Route("/archives/{month}/{year}/page/{page}", name="blog_articles_from_archives_page")
	 * @Rights("BLOG_ACCESS")
	 */
	public function archiveActionPage($month, $year, $page)
	{
		$blog = $this->getBlog();
		$startDate = strtotime($year . '-' . $month . '-01 00:00:00');
		if ($month == 12) {
			$month = '01';
		}
		else {
			$month++;
		}
		$endDate = strtotime($year . '-' . $month . '-01 23:59:59') - 86400;
		
        return $this->renderBlog($page, $blog, array(
			'BlogArticleQuery' => BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
				->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
				->addAscendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
				->add(BlogArticlePeer::BLOG_ID, $blog->getId())
				->add(BlogArticlePeer::PUBLISHED_AT, $startDate, \Criteria::GREATER_EQUAL)
				->addAnd(BlogArticlePeer::PUBLISHED_AT, $endDate, \Criteria::LESS_EQUAL)
		), array('archive_month' => $month, 'archive_year' => $year));
	}
	
	/**
	 * @Route("/auteur/{slug}", name="blog_articles_from_author")
	 * @Rights("BLOG_ACCESS")
	 */
	public function authorAction($slug)
	{
		return $this->authorActionPage($slug, 1);
	}
	
	/**
	 * @Route("/auteur/{slug}/page/{page}", name="blog_articles_from_author_page")
	 * @Rights("BLOG_ACCESS")
	 */
	public function authorActionPage($slug, $page)
	{
		$blog = $this->getBlog();
        return $this->renderBlog($page, $blog, array(
			'BlogArticleQuery' => BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
				->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
				->addAscendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
				->add(BlogArticlePeer::BLOG_ID, $blog->getId())
				->add(UserPeer::SLUG, $slug)
		), array('author_slug' => $slug));
	}
	
	/**
	 * @Route("/article/{slug}", name="blog_article_permalink")
	 * @Rights("BLOG_ACCESS")
	 */
	public function permalinkActionPage($slug)
	{
		$blog = $this->getBlog();
        return $this->renderBlog(1, $blog, array(
			'BlogArticleQuery' => BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
				->add(BlogArticlePeer::BLOG_ID, $blog->getId())
				->add(BlogArticlePeer::SLUG, $slug)
		));
	}
	
	/**
	 * @param int $page
	 * @param \BNS\App\CoreBundle\Model\Blog $blog
	 * @param array $queries
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	private function renderBlog($page, Blog $blog, array $queries = array(), array $parameters = array())
	{
		if (!isset($queries['BlogArticleQuery'])) {
			$queries['BlogArticleQuery'] = BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
				->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
				->addAscendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
				->add(BlogArticlePeer::BLOG_ID, $blog->getId())
			;
		}
		$pager = $queries['BlogArticleQuery']->paginate($page, 5);
		// Get articles
		$articles = $pager->getResults();
		
		$articles->populateRelation('BlogArticleCategory', isset($queries['BlogArticleCategory']) ? $queries['BlogArticleCategory'] : BlogArticleCategoryQuery::create()
			->joinWith('BlogCategory')
		);
		
		// Finally, replace articles in blog
		$blog->replaceBlogArticles($articles);
		
		$parameters['blog']		= $blog;
		$parameters['pager']	= $pager;
		
		return $this->render('BNSAppBlogBundle:Front:index.html.twig', $parameters);
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\Blog $blog
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function getArchivesAction(Blog $blog)
	{
		$archiveBlog = clone $blog;
		$archiveBlog->replaceBlogArticles(
			BlogArticleQuery::create()
				->isPublished()
				->add(BlogArticlePeer::BLOG_ID, $archiveBlog->getId())
			->find()
		);
		
		return $this->render('BNSAppBlogBundle:Block:front_block_archives.html.twig', array(
			'blog' => $archiveBlog
		));
	}
	
	/**
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function getContextBlogsAction()
	{
		$currentGroup = $this->get('bns.right_manager')->getContext();
		$allGroups = $this->get('bns.right_manager')->getGroupsWherePermission(Blog::PERMISSION_BLOG_ACCESS);
		$groups = array();
		
		foreach ($allGroups as $group) {
			if ($group->getId() == $currentGroup['id']) {
				continue;
			}
			
			$groups[] = $group;
		}
		// TODO check if module is activated
		
		return $this->render('BNSAppBlogBundle:Block:front_block_switch_context.html.twig', array(
			'groups' => $groups
		));
	}
	
	/**
	 * @return Blog 
	 * 
	 * @throws NotFoundHttpException
	 */
	private function getBlog()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$blogs = BlogQuery::create()
			->joinWith('BlogCategory', Criteria::LEFT_JOIN)
			->joinWith('Resource', Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->addAscendingOrderByColumn(BlogCategoryPeer::LEFT)
		->find(); // Automatic ORM join, do NOT use findOneBy
		
		if (!isset($blogs[0])) {
			throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
		}
		
		return $blogs[0];
	}
}