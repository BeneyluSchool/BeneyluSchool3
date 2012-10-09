<?php

namespace BNS\App\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Model\BlogArticleCategoryQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogCategoryPeer;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\Blog;

/**
 * @Route("/gestion")
 * 
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackArticleController extends Controller
{
	/**
     * @Route("/articles", name="blog_manager_articles", options={"expose"=true})
	 * @Rights("BLOG_ACCESS_BACK")
     */
	public function getArticlesAction($blog = null)
	{
		return $this->getArticles(1, $blog);
	}
	
	/**
     * @Route("/articles/page/{page}", name="blog_manager_articles_page")
	 * @Rights("BLOG_ACCESS_BACK")
     */
	public function getArticlesPageAction($page, $blog = null)
	{
		return $this->getArticles($page, $blog);
	}
	
	/**
	 * @param int $page
	 * @param \BNS\App\CoreBundle\Model\Blog $blog
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
    private function getArticles($page, Blog $blog = null)
    {
		if ('POST' == $this->getRequest()->getMethod()) {
			$request = $this->getRequest();
			$sessionName = 'blog_articles_filters';
			
			if ($request->get('category', false) !== false) {
				$filterName	= 'categories';
				$parameter	= 'category';
				
				// Validate category id
				$context = $this->get('bns.right_manager')->getContext();
				$category = BlogCategoryQuery::create()
					->join('Blog')
					->add(BlogPeer::GROUP_ID, $context['id'])
				->findPk($request->get($parameter));
				
				if (null == $category) {
					return $this->getBackArticlesPage(1);
				}
			}
			else if ($this->getRequest()->get('filter', false) !== false) {
				$filterName	= 'filters';
				$parameter	= 'filter';
				
				// Validate filter
				if ($request->get($parameter) != 'programmed') {
					$valuesSet = BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS);
					if (!isset($valuesSet[$request->get($parameter)])) {
						return $this->getBackArticlesPage(1);
					}
				}
			}
			else {
				return $this->getBackArticlesPage(1);
			}
			
			$filters = $request->getSession()->get($sessionName);
			if (null != $filters && isset($filters[$filterName])) {
				if ($request->get('is_enabled') == 'true') {
					$filters[$filterName][] = $request->get($parameter);
				}
				else {
					foreach ($filters[$filterName] as $key => $filter) {
						if ($filter == $request->get($parameter)) {
							unset($filters[$filterName][$key]);
							break;
						}
					}
				}
				
				$request->getSession()->set($sessionName, $filters);
			}
			else {
				if (null == $filters) {
					$request->getSession()->set($sessionName, array(
						$filterName => array($request->get($parameter))
					));
				}
				else {
					$filters[$filterName] = array($request->get($parameter));
					$request->getSession()->set($sessionName, $filters);
				}
			}
		}
		
		return $this->renderArticles($page, $blog);
    }
	
	/**
	 * 
	 * @param int $page
	 * @param \BNS\App\CoreBundle\Model\Blog $blog
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	private function renderArticles($page, Blog $blog = null)
	{
		if (null == $blog) {
			$blog = $this->getBlog();
		}
		
		$blog->replaceBlogArticles(
			BlogArticleCategoryQuery::injectCategoriesFromArticles(
				BlogArticleCommentQuery::injectCommentFromArticles(
					BlogArticleQuery::getArticlesFromBlog($blog, $pager, $page, $this->getRequest()->getSession()->get('blog_articles_filters')), null
				)
			)
		);
		
		return $this->render('BNSAppBlogBundle:Article:back_article_list.html.twig', array(
			'blog'			=> $blog,
			'pager'			=> $pager,
			'isAjaxCall'	=> $this->getRequest()->isXmlHttpRequest()
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
			->joinWith('BlogCategory', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->addAscendingOrderByColumn(BlogCategoryPeer::LEFT)
		->find(); // Automatic ORM join, do NOT use findOneBy
		
		if (!isset($blogs[0])) {
			throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
		}
		
		return $blogs[0];
	}
}