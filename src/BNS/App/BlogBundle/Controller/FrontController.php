<?php

namespace BNS\App\BlogBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategoryQuery;
use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogCategoryPeer;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\ProfileQuery;
use BNS\App\CoreBundle\Model\UserPeer;

use Criteria;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;

/**
 * @author <sylvain.lorinet@pixel-cookers.com>
 */
class FrontController extends Controller
{
    protected function canReadArticle(BlogArticle $article)
    {
        $rm = $this->get('bns.right_manager');
        $blogIds = $article->getBlogs()->getPrimaryKeys();
        //On forbid si l'utilisateur n'a pas le droit de voir cet article en back office
        $canRead = false;
        foreach($rm->getGroupsWherePermission("BLOG_ACCESS") as $group)
        {
            if(in_array($group->getBlog()->getId(), $blogIds))
            {
                $canRead = true;
            }
        }
        $rm->forbidIf(!$canRead);
        return true;
    }


	/**
     * @Route("/", name="BNSAppBlogBundle_front")
	 * @Rights("BLOG_ACCESS")
     */
    public function indexAction()
    {
        $this->get('stat.blog')->visit();

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
	 * @Route("/categorie/{categorySlug}", name="blog_articles_from_category")
	 * @Rights("BLOG_ACCESS")
	 */
	public function categoryAction($categorySlug)
	{
        return $this->categoryPageAction($categorySlug, 1);
	}

	/**
	 * @Route("/categorie/{categorySlug}/page/{page}", name="blog_articles_from_category_page")
	 * @Rights("BLOG_ACCESS")
	 */
	public function categoryPageAction($categorySlug, $page)
	{
		$blogCategory = BlogCategoryQuery::create('bc')
			->where('bc.Slug = ?', $categorySlug)
		->findOne();

		if (null == $blogCategory) {
			return $this->redirect($this->generateUrl('BNSAppBlogBundle_front'));
		}

		$blog = $this->getBlog();
		$blogArticleQuery = BlogArticleQuery::create()
            ->distinct()
            ->filterByBlog($blog)
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->join('BlogArticleCategory bac')
			->join('bac.BlogCategory bc')
			->isPublished()
			->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
			->addDescendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
		;

		// Is parent ?
		if ($blogCategory->getLevel() == 1) {
			$childrenCategories = $blogCategory->getChildren();
			$categorySlugs = array($categorySlug);

			foreach ($childrenCategories as $childCategory) {
				$categorySlugs[] = $childCategory->getSlug();
			}

			$blogArticleQuery->where('bc.Slug IN ?', $categorySlugs);
		}
		else {
			// Match with only one category if child
			$blogArticleQuery->where('bc.Slug = ?', $categorySlug);
		}

        return $this->renderBlog($page, $blog, array(
			'BlogArticleQuery' => $blogArticleQuery
		), array(
			'category_slug'		=> $categorySlug,
			'pager_link'		=> 'blog_articles_from_category_page',
			'pager_parameters'	=> array(
				'categorySlug' => $categorySlug
			)
		));
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
        $paramMonth = $month;
        $blog = $this->getBlog();
        $startDate = strtotime($year . '-' . $month . '-01 00:00:00');
        if ($month == 12) {
            $month = '01';
            $endYear = $year + 1;
        } else {
            $month++;
            $endYear = $year;
        }
        $endDate = strtotime($endYear . '-' . $month . '-01 23:59:59') - 86400;

        return $this->renderBlog($page, $blog, array(
            'BlogArticleQuery' => BlogArticleQuery::create()
                ->filterByBlog($blog)
                ->filterByPublishedAt([
                    'min' => $startDate,
                    'max' => $endDate
                ])
                ->isPublished()
                ->orderByIsStar(\Criteria::DESC)
                ->orderByPublishedAt(\Criteria::DESC)
                ->joinWith('User')
                ->joinWith('User.Profile')
                ->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)


        ), array(
            'archive_month'        => $month,
            'archive_year'        => $year,
            'pager_link'        => 'blog_articles_from_archives_page',
            'pager_parameters'    => array(
                'month'    => $paramMonth,
                'year'    => $year
            )
        ));
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
                ->filterByBlog($blog)
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
				->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
				->addDescendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
				->add(UserPeer::SLUG, $slug)
		), array(
			'author_slug'		=> $slug,
			'pager_link'		=> 'blog_articles_from_author_page',
			'pager_parameters'	=> array(
				'slug'	=> $slug
			)
		));
	}

	/**
	 * @Route("/article/{slug}", name="blog_article_permalink")
     * Pas d'annotation Rights car on peut arriver d'une notification
     *
     * @param string $slug
     * @param Request $request
     * @return Response
	 */
	public function permalinkActionPage($slug, Request $request)
	{
		$article = BlogArticleQuery::create()->findOneBySlug($slug);
		if (!$article) {
		    throw $this->createNotFoundException();
        }
        $this->canReadArticle($article);
        $blog = $this->getBlog();

        // article is not visible in current blog (but user still has access), so change context to group of first blog
        // where article is published
        if (!in_array($blog->getId(), $article->getBlogs()->getPrimaryKeys())) {
            return $this->get('bns.right_manager')->changeContextToSeeBlogArticle(
                $request,
                $article,
                'blog_article_permalink',
                ['slug' => $slug]
            );
        }

        //On reprend le déroulement normal
        return $this->renderBlog(1, $blog, array(
			'BlogArticleQuery' => BlogArticleQuery::create()
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->isPublished()
                ->filterByBlog($blog)
				->add(BlogArticlePeer::SLUG, $slug)
		));
	}

	/**
	 * @param int $page
	 * @param Blog $blog
	 * @param array $queries
	 *
	 * @return Response
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
				->addDescendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT)
                ->filterByBlog($blog)
			;
		}
		$pager = $queries['BlogArticleQuery']->paginate($page, 5);
		// Get articles
		$articles = $pager->getResults();

		$articles->populateRelation('BlogArticleCategory', isset($queries['BlogArticleCategory']) ? $queries['BlogArticleCategory'] : BlogArticleCategoryQuery::create()
			->joinWith('BlogCategory')
		);

		$commentQuery = BlogArticleCommentQuery::create('c')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->orderBy('c.Date', \Criteria::DESC)
		;

		if ($this->get('bns.right_manager')->hasRight('PROFILE_ADMINISTRATION')) {
			$commentQuery->where('c.Status != ?', 'REFUSED');
		}
		else {
			$commentQuery->where('c.Status = ?', 'VALIDATED')
				->orWhere('c.AuthorId = ?', $this->getUser()->getId())
				->where('c.Status != ?', 'REFUSED')
			;
		}

		$articles->populateRelation('BlogArticleComment', $commentQuery);

		// Finally, replace articles in blog
		$blog->replaceBlogArticles($articles);

		if (!isset($parameters['archive_month'])) {
			$parameters['archive_month'] = null;
			$parameters['archive_year'] = null;
		}

		$parameters['blog']		= $blog;
		$parameters['pager']	= $pager;

        $parameters['closeYerbook'] = true;

        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager')->setUser($user);
        $groupId = $blog->getGroupId();
        if ('fr' === $user->getLang()
            && !$this->getParameter('yerbook_order_closed')
            && $userManager->hasRight('BLOG_YERBOOK_SEE', $groupId)
            && $userManager->hasRight('SPOT_ACCESS', $groupId)
            && !$userManager->hasRight('YERBOOK_ACCESS', $groupId))
        {
            $parameters['closeYerbook'] = false;
            $profileId = $user->getProfileId();
            if ($profileId) {
                $closeYerbook = ProfileQuery::create()
                    ->filterById($profileId)
                    ->select('closeYerbook')
                    ->findOne();

                if ($closeYerbook) {
                    $parameters['closeYerbook'] = $closeYerbook;
                }
            }
        }

		return $this->render('BNSAppBlogBundle:Front:index.html.twig', $parameters);
	}

    /**
    * @Route("/close-yerbook", name="blog_close_yerbook")
    */
    public function closeYerbookHeaderAction(Request $request) {

        $csrfProvider = $this->get('security.csrf.token_manager');

        $token = new CsrfToken('closeYerbook_', $request->get('_token'));
        if (!$csrfProvider->isTokenValid($token)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('CSRF token is invalid.');
        }
 	    // refresh token to avoid duplicate call
        $csrfProvider->refreshToken('closeYerbook_');

        $profileId = $this->getUser()->getProfileId();
        if ($profileId) {
            $profile = ProfileQuery::create()
                ->findPk($profileId);

            $profile->setCloseYerbook(true)->save();
        }

        return $this->redirect($this->generateUrl('BNSAppBlogBundle_front'));
    }

	/**
	 * @param Blog $blog
	 *
	 * @return Response
	 */
	public function getArchivesAction(Blog $blog, $archiveMonth = null, $archiveYear = null)
	{
		$archiveBlog = clone $blog;
		$archiveBlog->replaceBlogArticles(
			BlogArticleQuery::create()
				->isPublished()
                ->filterByBlog($archiveBlog)
			->find()
		);

		$parameters = array(
			'blog'	=> $archiveBlog
		);

		if (null != $archiveMonth) {
			$parameters['archive_month'] = $archiveMonth - 1;
			$parameters['archive_year'] = $archiveYear;
		}

		return $this->render('BNSAppBlogBundle:Block:front_block_archives.html.twig', $parameters);
	}

	/**
	 * @return Response
	 */
	public function getContextBlogsAction()
	{
		$currentGroup = $this->get('bns.right_manager')->getContext();
		$allGroups = $this->get('bns.right_manager')->getGroupsWherePermission(Blog::PERMISSION_BLOG_ACCESS);
		$groups = new \PropelObjectCollection();

		foreach ($allGroups as $group) {
			if ($group->getId() == $currentGroup['id']) {
				continue;
			}

			$groups[] = $group;
		}

		$blogs = BlogQuery::create('b')
			->joinWith('Resource', \Criteria::LEFT_JOIN)
			->where('b.GroupId IN ?', $groups->getPrimaryKeys())
		->find();

		foreach ($groups as $group) {
			foreach ($blogs as $blog) {
				if ($group->getId() == $blog->getGroupId()) {
					$group->addBlog($blog);
					break;
				}
			}
		}
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
			->joinWith('Resource', Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
		->find(); // Automatic ORM join, do NOT use findOneBy

		if (!isset($blogs[0])) {
			throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
		}

		return $blogs[0];
	}
}
