<?php

namespace BNS\App\BlogBundle\Controller;

use BNS\App\BlogBundle\Form\Model\BlogArticleFormModel;
use BNS\App\BlogBundle\Form\Type\BlogArticleType;
use BNS\App\BlogBundle\Form\Type\BlogType;
use BNS\App\CommentBundle\Form\Type\CommentType;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategoryQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Utils\Crypt;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/gestion")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackController extends Controller
{
    /**
     * @Route("/", name="BNSAppBlogBundle_back")
     * @Rights("BLOG_ACCESS_BACK")
     */
    public function indexAction(Request $request)
    {

        $context = $this->get('bns.right_manager')->getContext();
        $blog = $this->getCurrentBlog();

        if (!isset($blog)) {
            throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
        }

        // Gestion des filtres d'articles
        $this->getRequest()->getSession()->remove('blog_articles_filters');

        // Récupération des indicateurs pour les filtres
        $indicatorsResultQuery = BlogArticleQuery::create('a')
            ->filterByBlog($blog)
            ->withColumn('count(id)', 'nb_article')
            ->select(array('a.Status', 'nb_article'))
            ->groupBy('a.Status')
        ;

        // Si élève, on ne récupère que les siens
        if (!$this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION')) {
            $indicatorsResultQuery->where('a.AuthorId = ?', $this->getUser()->getId());
        }

        $indicatorsResult = $indicatorsResultQuery ->find();

        $indicators = array();
        $statuses = BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS);
        foreach ($indicatorsResult as $indicator) {
            $indicators[$statuses[$indicator['a.Status']]] = $indicator['nb_article'];
        }

        // Les statuts PUBLISHED peuvent aussi être PROGRAMMED, on affine et on recalcule
        $programmedIndicatorQuery = BlogArticleQuery::create('a')
            ->filterByBlog($blog)
            ->where('a.Status = ?', 'PUBLISHED')
            ->where('a.PublishedAt > ?', time())
        ;

        // Si élève, on ne récupère que les siens
        if (!$this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION')) {
            $programmedIndicatorQuery->where('a.AuthorId = ?', $this->getUser()->getId());
        }

        $programmedIndicator = $programmedIndicatorQuery->count();

        if (0 < $programmedIndicator) {
            $indicators['PUBLISHED'] -= $programmedIndicator;
            $indicators['PROGRAMMED'] = $programmedIndicator;
        }

        $this->get('stat.blog')->visit();
        $hasYerbook = $this->showYerbookTeasing($blog);

        return $this->render('BNSAppBlogBundle:Back:index_manager.html.twig', array(
            'blog'				=> $blog,
            'filterIndicators'	=> $indicators,
            'hasYerbook'        => $hasYerbook
        ));
    }

    /**
     * @Route("/exporter-blog", name="blog_manager_export")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function exportAction(Request $request)
    {
        $type = $request->get('type');
        $include_comments = $request->get('include_comments');
        $order = $request->get('order');

        return $this->exportArticlesToPDF($type, $include_comments, $order);
    }

    /**
     * @Route("/nouvel-article", name="blog_manager_new_article")
     * @Rights("BLOG_ACCESS_BACK")
     */
    public function newArticleAction()
    {
        $blog = $this->getCurrentBlog();

        $categories = $blog->getRootCategory()->getDescendants();

        $form = $this->createForm(new BlogArticleType(
            $this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION'),
            $this->get('bns.right_manager')->hasRight('BLOG_PUBLISH'),
            false,
            $this->get('bns.right_manager')->getGroupsWherePermission('BLOG_ACCESS_BACK'),
            $categories,
            $this->generateUrl('blog_category_api_post_category', ['id' => $blog->getId(), 'version' => '1.0']),
            $blog->getId()
        ), new BlogArticleFormModel($this->get("stat.blog"), null, $blog));

        return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
            'blog'			=> $blog,
            'form'			=> $form->createView(),
            'article'		=> $form->getData()->getArticle(),
            'isEditionMode'	=> false
        ));
    }

    /**
     * @Route("/nouvel-article/terminer", name="blog_manager_new_article_finish")
     * @Rights("BLOG_ACCESS_BACK")
     */
    public function finishNewArticleAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $article = new BlogArticle();
            $article->setStatus('FINISHED');

            $blog = $this->getCurrentBlog();

            $categories = $blog->getRootCategory()->getDescendants();

            $model = new BlogArticleFormModel($this->get("stat.blog"), $article, $blog);

            $form = $this->createForm(
                new BlogArticleType(
                    $this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION'),
                    $this->get('bns.right_manager')->hasRight('BLOG_PUBLISH'),
                    true,
                    $this->get('bns.right_manager')->getGroupsWherePermission('BLOG_ACCESS_BACK'),
                    $categories,
                    $this->generateUrl('blog_category_api_post_category', ['id' => $blog->getId(), 'version' => '1.0']),
                    $blog->getId()),
                $model
            );

            $form->handleRequest($request);
            $this->get('bns.media.manager')->bindAttachments($model->getArticle(), $request);
            if ($form->isValid()) {
                $model = $form->getData();

                // Finally
                $model->save($this->get('bns.right_manager'), $this->getUser(), $this->get('bns.media.manager'), $request, $blog);

                return $this->redirect($this->generateUrl('blog_manager_article_visualisation', array(
                    'articleSlug' => $model->getArticle()->getSlug()
                )));
            }

            return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
                'blog'          => $blog,
                'form'          => $form->createView(),
                'article'       => $form->getData()->getArticle(),
                'isEditionMode' => false
            ));
        }

        return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
    }

    /**
     * @Route("/article/{articleSlug}/editer", name="blog_manager_edit_article", options={"expose"=true})
     * On enlève l'annotation Rights car nous pouvons arriver des notifications
     */
    public function editArticleAction($articleSlug, Request $request)
    {
        $article = BlogArticleQuery::create()->findOneBySlug($articleSlug);
        if (!$article) {
            throw $this->createNotFoundException();
        }

        if (!$this->get('bns_app_blog.blog_manager')->canEditArticle($article)) {
            if ($this->get('bns_app_blog.blog_manager')->canManageArticle($article)) {
                // user can manage bug cannot edit this post we redirect him to list page
                return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
            }
            throw $this->createAccessDeniedException();
        }

        $blog = $this->getCurrentBlog();
        $categories = $blog->getRootCategory()->getDescendants();
        $linkedGroupIds = BlogQuery::create()
            ->useBlogArticleBlogQuery()
                ->filterByArticleId($article->getId())
            ->endUse()
            ->select(['GroupId'])
            ->find()
            ->getArrayCopy()
        ;

        $linkedCategories = BlogCategoryQuery::create()
            ->filterByBlogId($blog->getId(), \Criteria::NOT_EQUAL)
            ->useBlogArticleCategoryQuery()
                ->filterByArticleId($article->getId())
            ->endUse()
            ->find()
        ;

        $linkedGroupIds = array_map('intval', $linkedGroupIds);
        $rightManager = $this->get('bns.right_manager');
        $allowedGroupIds = $rightManager->getGroupIdsWherePermission('BLOG_ACCESS_BACK');
        $notAllowedGroupIds = array_diff($linkedGroupIds, $allowedGroupIds);
        $allowedGroups = GroupQuery::create()->filterById($allowedGroupIds)->find();

        $isEditionMode = true;
        $form = $this->createForm(
            new BlogArticleType(
                $rightManager->hasRight('BLOG_ADMINISTRATION'),
                $rightManager->hasRight('BLOG_PUBLISH') && $article->getAuthorId() === $this->getUser()->getId(),
                true,
                $allowedGroups,
                $categories,
                $this->generateUrl('blog_category_api_post_category', ['id' => $blog->getId(), 'version' => '1.0']),
                $blog->getId()
            ),
            new BlogArticleFormModel($this->get("stat.blog"), clone $article, $this->getCurrentBlog())
        );

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            $this->get('bns.media.manager')->bindAttachments($article, $request);
            if ($form->isValid()) {
                /** @var BlogArticleFormModel $model */
                $model = $form->getData();

                // Finally
                $model->save($rightManager, $this->getUser(), $this->get('bns.media.manager'), $request, $this->getCurrentBlog());

                $article = $model->getArticle();
                if (count($notAllowedGroupIds) > 0) {
                    // set back excluded blog id
                    foreach (BlogQuery::create()->filterByGroupId($notAllowedGroupIds)->find() as $notAllowedBlog) {
                        $article->addBlog($notAllowedBlog);
                    }
                }
                if ($linkedCategories->count() > 0) {
                    foreach ($linkedCategories as $linkedCategory) {
                        $article->addBlogCategory($linkedCategory);
                    }
                }
                $article->save();

                // Flash message
                $message = $this->get('translator')->trans('FLASH_ARTICLE_WAS_MODIFY', array(), 'BLOG');

                if ($rightManager->isAdult()) {
                    if ($this->getUser()->getId() == $model->getArticle()->getAuthorId()) {
                        $message = $this->get('translator')->trans('ADULT_ARTICLE_WAS_MODIFY', array(), 'BLOG');
                    } else {
                        $message = $this->get('translator')->trans('ARTICLE_WAS_MODIFY_WITH_SUCCESS', array(), 'BLOG');
                    }
                }

                if ($model->getArticle()->isFinished() && !$rightManager->hasRight('BLOG_ADMINISTRATION')) {
                    $message .= $this->get('translator')->trans('FLASH_MODERATION_IN_PROGRESS', array(), 'BLOG');
                }
                $this->get('session')->getFlashBag()->add('success', $message);

                // force recalculation of article size
                $this->get('snc_redis.default')->del('yerbook_height_' . $article->getId());

                return $this->redirect($this->generateUrl('blog_manager_article_visualisation', array(
                    'articleSlug' => $model->getArticle()->getSlug()
                )));
            }
        }

        return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
            'blog'			=> $blog,
            'form'			=> $form->createView(),
            'article'		=> $article,
            'isEditionMode'	=> $isEditionMode
        ));
    }

    /**
     * @param Blog $blog
     * @param boolean $isForm
     *
     * @return Response
     */
    public function loadCategoriesBlockAction(Blog $blog, $article = null, $form = null)
    {
        return $this->render('BNSAppBlogBundle:Block:back_block_categories.html.twig', array(
            'blog'			=> $blog,
            'isEditionMode'	=> null != $article,
            'article'		=> $article,
            'form'			=> $form
        ));
    }

    /**
     * @return Response
     *
     * @throws \RuntimeException
     */
    public function getCategoryIconsAction()
    {
        $dirPath = $this->container->getParameter('kernel.root_dir') . '/../web/medias/images/icons/categories';
        $dir = opendir($dirPath);
        if (!$dir) {
            throw new \RuntimeException('Can NOT open the directory : ' . $dirPath . ' !');
        }

        $images = array();
        while ($fileName = @readdir($dir)) {
            if (is_dir($fileName)) {
                continue;
            }

            $images[substr($fileName, 0, -4)] = $fileName;
        }

        closedir($dir);

        /*
         * $image
         *	 key: class name
         *   value: image url path
         */

        return $this->render('BNSAppBlogBundle:Category:back_block_categories_icons_list.html.twig', array(
            'images' => $images
        ));
    }

    /**
     * @Route("/personnalisation", name="blog_manager_custom")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function customAction(Request $request)
    {
        $context = $this->get('bns.right_manager')->getContext();
        $blog = BlogQuery::create()
            ->joinWith('Resource', \Criteria::LEFT_JOIN)
            ->add(BlogPeer::GROUP_ID, $context['id'])
            ->findOne();

        if (!$blog) {
            throw $this->createNotFoundException('The blog with the group id : ' . $context['id']  . ' is NOT found ! ');
        }

        $form = $this->createForm(new BlogType(), $blog);
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $blog = $form->getData();
                $blog->save();

                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('BLOG_UPDATE_SUCCESS', array(), "BLOG"));

                // Redirect to avoid refresh
                return $this->redirect($this->generateUrl('blog_manager_custom'));
            }
        }

        return $this->render('BNSAppBlogBundle:Custom:index.html.twig', array(
            'blog'          => $blog,
            'form'          => $form->createView(),
            'hasYerbook'    => $this->showYerbookTeasing($blog)
        ));
    }

    /**
     * @Route("/yerbook", name="blog_manager_yerbook")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function yerbookAction()
    {
        $blog = $this->getCurrentBlog();

        if ($this->showYerbookTeasing($blog)) {
            return $this->render('BNSAppBlogBundle:Yerbook:index.html.twig');
        }

        return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
    }

    /**
     * @Route("/commentaires", name="blog_manager_comment")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function commentsAction($page = 1)
    {
        $blog = $this->getCurrentBlog();
        //Statistic action
        $this->get("stat.blog")->newCommentArticle();

        $hasYerbook = $this->showYerbookTeasing($blog);

        return $this->render('BNSAppBlogBundle:Comment:index.html.twig', array(
            'blog'		=> $blog,
            'namespace'	=> Crypt::encrypt('BNS\\App\\CoreBundle\\Model\\BlogArticleComment'),
            'page'		=> $page,
            'editRoute'	=> 'blog_manager_comment_moderation_edit',
            'hasYerbook' => $hasYerbook
        ));
    }

    /**
     * @Route("/categories", name="blog_manager_categories")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function categoriesAction()
    {
        $blog = $this->getCurrentBlog();

        return $this->render('BNSAppBlogBundle:Custom:categories.html.twig', array(
            'blog' => $blog,
            'hasYerbook' => $this->showYerbookTeasing($blog),
        ));
    }

    /**
     * @Route("/visualisation/{articleSlug}", name="blog_manager_article_visualisation")
     *
     * @param Request $request
     * @param string $articleSlug
     */
    public function visualisationAction($articleSlug, Request $request)
    {
        $article = BlogArticleQuery::create()->findOneBySlug($articleSlug);
        if (!$article) {
            throw $this->createNotFoundException();
        }
        if (!$this->get('bns_app_blog.blog_manager')->canManageArticle($article)) {
            throw $this->createAccessDeniedException();
        }
        $blog = $this->getCurrentBlog();

        // article is not visible in current blog (but user still has access), so change context to group of first blog
        // where article is published
        if (!in_array($blog->getId(), $article->getBlogs()->getPrimaryKeys())) {
            return $this->get('bns.right_manager')->changeContextToSeeBlogArticle(
                $request,
                $article,
                'blog_manager_article_visualisation',
                ['articleSlug' => $articleSlug]
            );
        }

        return $this->render('BNSAppBlogBundle:Article:back_article_visualisation.html.twig', array(
            'article'	=> $article,
            'blog' => $blog
        ));
    }

    /**
     * @Route("/commentaire/{id}/editer", name="blog_manager_comment_edit")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function editComment($id, $isModeration = false)
    {
        $blog = $this->getCurrentBlog();

        $namespace = 'BNS\\App\\CoreBundle\\Model\\BlogArticleComment';
        $form = $this->createForm(new CommentType());
        if ($this->getRequest()->isMethod('POST')) {
            $form->bind($this->getRequest());

            if ($form->isValid()) {
                $comment = $form->getData();
                $comment->save($namespace);

                $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('COMMENT_UPDATE_SUCCESS', array(), "BLOG"));

                // Choose the right redirection, moderation or object visualisation
                if ($isModeration) {
                    $view = 'blog_manager_comment';
                    $params = array();
                }
                else {
                    $view = 'blog_manager_article_visualisation';
                    $params = array(
                        'articleSlug' => $comment->getComment()->getObject()->getSlug()
                    );
                }

                return $this->redirect($this->generateUrl($view, $params));
            }
        }

        return $this->forward('BNSAppCommentBundle:BackComment:renderEditComment', array(
            'id'		    => $id,
            'namespace'	    => $namespace,
            'extendsView'   => 'BNSAppBlogBundle:Comment:comment_form.html.twig',
            'isModeration'	=> $isModeration,
            'form'			=> $form,
            'extraParams'   => array(
                'blog' => $blog
            )
        ));
    }

    /**
     * @Route("/commentaires/moderation/{id}/editer", name="blog_manager_comment_moderation_edit")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function editModerationComment($id)
    {
        return $this->editComment($id, true);
    }

    /**
     * @Route("/exporter-articles", name="blog_manager_export_articles")
     * @Rights("CLASSROOM_ACCESS_BACK")
     */
    public function exportArticlesToPDF($type = 'all', $include_comments=false, $order=false)
    {
        $blog = $this->getCurrentBlog();
        if($order) {
            $articles = BlogArticleQuery::create()
                ->filterByBlog($blog)
                ->joinWith('User')
                ->joinWith('User.Profile')
                ->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
                ->isPublished()
                ->addAscendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT);
        } else {
            $articles = BlogArticleQuery::create()
                ->filterByBlog($blog)
                ->joinWith('User')
                ->joinWith('User.Profile')
                ->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
                ->isPublished()
                ->addDescendingOrderByColumn(BlogArticlePeer::IS_STAR)
                ->addDescendingOrderByColumn(BlogArticlePeer::PUBLISHED_AT);
        }

        if($type != 'all')
        {
            $category = BlogCategoryQuery::create()->filterById($type)->findOne();
            if($category->getBlogId() != $blog->getId())
            {
                $this->get('bns.right_manager')->forbidIf(true);
            }
            $articles->useBlogArticleCategoryQuery()->filterByCategoryId($category->getId())->endUse();
        }
        $articles = $articles->find();

        $articles->populateRelation('BlogArticleCategory', BlogArticleCategoryQuery::create()
            ->joinWith('BlogCategory')
        );

        $html = $this->renderView('BNSAppBlogBundle:Export:export_article.html.twig', array(
            'blog'     => $blog,
            'articles' => $articles,
            'include_comments' => $include_comments
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="export-blog.pdf"'
            )
        );
    }

    /**
     * @param Blog $blog
     * @return bool
     */
    protected function showYerbookTeasing(Blog $blog)
    {
        $groupId = $blog->getGroupId();
        if (!$groupId) {
            return false;
        }
        $user = $this->getUser();
        $userManager = $this->get('bns.user_manager')->setUser($user);

        return 'fr' === $user->getLang()
            && !$this->getParameter('yerbook_order_closed')
            && $userManager->hasRight('BLOG_YERBOOK_SEE', $groupId)
            && $userManager->hasRight('SPOT_ACCESS', $groupId)
            && !$userManager->hasRight('YERBOOK_ACCESS', $groupId);
    }

    protected function getCurrentBlog()
    {
        if (!isset($this->currentBlog)) {
            $this->currentBlog = $this->get('bns.right_manager')->getCurrentGroup()->getBlog();
        }

        return $this->currentBlog;
    }

}
