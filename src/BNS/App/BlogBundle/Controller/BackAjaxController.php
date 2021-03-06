<?php

namespace BNS\App\BlogBundle\Controller;

use BNS\App\BlogBundle\Form\Model\BlogArticleFormModel;
use BNS\App\CoreBundle\Controller\BaseController;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\GroupQuery;
use FOS\RestBundle\Util\Codes;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\BlogBundle\Form\Type\BlogArticleType;

/**
 * @Route("/gestion")
 */
class BackAjaxController extends BaseController
{
    /**
     * @Route("/brouillon/sauvegarder/{slug}", name="blog_manager_draft_save", options={"expose"=true})
     * @Rights("BLOG_ACCESS_BACK")
     */
    public function saveDraftAction(Request $request, $slug = null)
    {
        if (!$request->isMethod('PATCH')) {
            return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
        }

        $article = BlogArticleQuery::create()
            ->filterBySlug($slug, \Criteria::EQUAL)
            ->findOne();

        if ($slug && !$article) {
            throw $this->createNotFoundException();
        } elseif (!$article) {
            $article = new BlogArticle();
            $article->setStatus(BlogArticlePeer::STATUS_DRAFT);
            $article->setAuthor($this->getUser());
        }
        $blogManager = $this->get('bns_app_blog.blog_manager');
        $blog = $blogManager->getCurrentBlog();
        $categories = $blog->getRootCategory()->getDescendants();
        $linkedGroupIds = [];
        $linkedCategories = [];
        if (!$article->isNew()) {
            if (!$blogManager->canEditArticle($article)) {
                throw $this->createAccessDeniedException();
            }
            $linkedGroupIds = BlogQuery::create()
                ->useBlogArticleBlogQuery()
                    ->filterByArticleId($article->getId())
                ->endUse()
                ->select(['GroupId'])
                ->find()
                ->getArrayCopy()
            ;
            $linkedGroupIds = array_map('intval', $linkedGroupIds);

            $linkedCategories = BlogCategoryQuery::create()
                ->filterByBlogId($blog->getId(), \Criteria::NOT_EQUAL)
                ->useBlogArticleCategoryQuery()
                    ->filterByArticleId($article->getId())
                ->endUse()
                ->find()
            ;

        }
        $rightManager = $this->get('bns.right_manager');

        $allowedGroupIds = $rightManager->getGroupIdsWherePermission('BLOG_ACCESS_BACK');
        $notAllowedGroupIds = array_diff($linkedGroupIds, $allowedGroupIds);
        $allowedGroups = GroupQuery::create()->filterById($allowedGroupIds)->find();

        $canSchedule = $this->hasFeature('blog_schedule');

        $form = $this->createForm(
            new BlogArticleType(
                $rightManager->hasRight('BLOG_ADMINISTRATION'),
                true,
                !$article->isNew(),
                $allowedGroups,
                $categories,
                $this->generateUrl('blog_category_api_post_category', ['id' => $blog->getId(), 'version' => '1.0']),
                $blog->getId(),
                $canSchedule
            ),
            new BlogArticleFormModel($this->get("stat.blog"), $this->get('bns.media_library.public_media_parser'), $article, $blog),
            [
                'validation_groups' => ['fu'],
                'method' => 'PATCH',
            ]
        );

        $form->handleRequest($request);
        if ($form->isValid()) {
            /** @var BlogArticleFormModel $model */
            $model = $form->getData();

            if (!$this->get('bns_app_blog.blog_manager')->canManageArticle($article)) {
                throw $this->createAccessDeniedException();
            }

            // Finally
            $model->save($rightManager, $this->getUser(), $this->get('bns.media.manager'), $request, $blog, true);

            $article = $model->getArticle();
            if (count($notAllowedGroupIds) > 0) {
                // set back excluded blog id
                foreach (BlogQuery::create()->filterByGroupId($notAllowedGroupIds)->find() as $notAllowedBlog) {
                    $article->addBlog($notAllowedBlog);
                }
            }
            if (count($linkedCategories) > 0) {
                foreach ($linkedCategories as $linkedCategory) {
                    $article->addBlogCategory($linkedCategory);
                }
            }
            $article->save();

            return new JsonResponse([
                'success' => true,
                'slug' => $article->getSlug(),
                'attributes' => [
                    [
                        'node' => '#form_new_article',
                        'attr' => 'action',
                        'value' => $this->generateUrl('blog_manager_edit_article', [
                            'articleSlug' => $article->getSlug()
                        ]),
                    ]
                ]
            ]);
        } else {
            $errors = array();
            /** @var \Symfony\Component\Form\Form $children */
            foreach ($form->getErrors(true, true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse([
                'errors' => $errors
            ], Codes::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @Route("/article/{articleSlug}/epingler", name="blog_manager_article_pin")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function starAction($articleSlug, Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $article = BlogArticleQuery::create()->filterBySlug($articleSlug)->findOne();
            if (!$article) {
                throw $this->createNotFoundException();
            }
            $this->get('bns_app_blog.blog_manager')->canEditArticle($article);
            $article->setIsStar(!$article->isStar());
            $article->save();

            return new Response();
        }

        throw new NotFoundHttpException('The page excepts AJAX header !');
    }

	/**
	 * @Route("/category/add", name="blog_manager_category_add")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function addCategoryAction($isManage = false, Request $request)
	{
		if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
			if (!$this->hasFeature('blog_categories')) {
				throw $this->createAccessDeniedException();
			}

			if (!$request->get('title', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}

			$context = $this->get('bns.right_manager')->getContext();
			$blog = BlogQuery::create()
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findOne();

			if (!$blog) {
				throw new NotFoundHttpException('The blog with group id ' . $context['id'] . ' is NOT found !');
			}

			$rootCategory = BlogCategoryQuery::create()->findRoot($blog->getId());
			$blogCategory = new BlogCategory();
			$blogCategory->setBlogId($context['id']);
			$blogCategory->setTitle($request->get('title'));
			$blogCategory->insertAsFirstChildOf($rootCategory);

			if (null != $request->get('iconName', null)) {
				$blogCategory->setIconClassname($request->get('iconName'));
			}

			$errors = $this->get('validator')->validate($blogCategory);
			if (isset($errors[0])) {
				throw new InvalidArgumentException($errors[0]->getMessage());
			}

			$blogCategory->save();

			$view = 'BNSAppBlogBundle:Category:back_block_categories_row.html.twig';
			if ($isManage) {
				$view = 'BNSAppBlogBundle:Category:back_categories_management_row.html.twig';
			}

			return $this->render($view, array(
				'category'		=> $blogCategory,
				'isEditionMode'	=> false,
				'isAdmin'		=> $this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION')
			));
		}

		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}

	/**
	 * @Route("/category/add/manage", name="blog_manager_category_add_management")
	 */
	public function addCategoryManageAction(Request $request)
	{
		return $this->addCategoryAction(true, $request);
	}

	/**
	 * @Route("/category/save", name="blog_manager_category_save", options={"expose"=true})
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function saveCategoriesAction(Request $request)
	{
		if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
			if (!$this->hasFeature('blog_categories')) {
				throw $this->createAccessDeniedException();
			}

			if ($request->get('categories', false) === false) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}

			$context = $this->get('bns.right_manager')->getContext();
			$categories = $request->get('categories');

			$blogCategories = BlogCategoryQuery::create()
				->join('Blog')
				->add(BlogPeer::GROUP_ID, $context['id'])
			->find();

			$blogCatById = array();
			$root = null;
			foreach ($blogCategories as $category) {
				$blogCatById[$category->getId()] = $category;

				if ($category->isRoot()) {
					$root = $category;
				}
			}

			if (null == $root) {
				throw new RuntimeException('There is not root category for blog id : '. $context['id']);
			}

			foreach ($categories as $parentCat) {
				$pCat = $blogCatById[$parentCat['id']];
				$pCat->moveToLastChildOf($root);

				if (isset($parentCat['children'])) {
					foreach ($parentCat['children'] as $subCategory) {
						$sCat = $blogCatById[$subCategory['id']];
						$sCat->moveToLastChildOf($pCat);
						$sCat->save();
					}
				}

				$pCat->save();
			}

			return new Response();
		}

		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}

	/**
	 * @Route("/category/edit", name="blog_manager_category_edit")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function editCategoryAction(Request $request)
	{
		if ($request->isMethod('POST') && $request->isXmlHttpRequest()) {
			if (!$this->hasFeature('blog_categories')) {
				throw $this->createAccessDeniedException();
			}

			if (!$request->get('title', false) || !$request->get('id', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}

			$categoryId = $request->get('id');
			$context = $this->get('bns.right_manager')->getContext();
			$category = BlogCategoryQuery::create()
				->join('Blog')
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findPk($categoryId);

			if (!$category) {
				throw new NotFoundHttpException('The category with id : ' . $categoryId . ' is not found !');
			}

			$category->setTitle($request->get('title'));
			if (false !== $request->get('iconName', false)) {
				$category->setIconClassname($request->get('iconName'));
			}

			$category->save();

			return new Response();
		}

		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}

	/**
	 * @Route("/category/delete", name="blog_manager_category_delete")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function deleteCategoryAction(Request $request)
	{
		if ($request->isXmlHttpRequest()) {
			if (!$this->hasFeature('blog_categories')) {
				throw $this->createAccessDeniedException();
			}

			if (!$request->get('id', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}

			$categoryId = $request->get('id');
			$context = $this->get('bns.right_manager')->getContext();
			$category = BlogCategoryQuery::create()
				->join('Blog')
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findPk($categoryId);

			if (!$category) {
				throw new NotFoundHttpException('The category with id : ' . $categoryId . ' is not found !');
			}

			$category->delete();

			return new Response();
		}

		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}

	/**
	 * @Route("/article/{articleId}/supprimer/confirmation/", name="blog_manager_article_delete_confirm")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function showDeleteArticleAction($articleId)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$article = BlogArticleQuery::create()
			->join('Blog')
			->add(BlogArticlePeer::ID, $articleId)
		->findOne();
        if (!$article) {
            throw $this->createNotFoundException();
        }

        if (!$this->get('bns.liaison_book_manager')->canEditArticle($article)) {
            throw $this->createAccessDeniedException();
        }

		return $this->renderDeleteArticleModalAction($article);
	}

	/**
	 * @param \BNS\App\CoreBundle\Model\BlogArticle $article
	 *
	 * @return
	 */
	public function renderDeleteArticleModalAction(BlogArticle $article)
	{
		return $this->render('BNSAppBlogBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'article' => $article,
			),
			'footerValues'	=> array(
				'article' => $article,
				'route'	 => $this->generateUrl('blog_manager_article_delete', array('articleId' => $article->getId()))
			),
			'title'	=> $article->getTitle()
		));
	}

	/**
	 * @Route("/article/{articleId}/supprimer", name="blog_manager_article_delete", options={"expose"=true})
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function deleteArticleAction($articleId)
	{
        $article = BlogArticleQuery::create()->findOneById($articleId);
        if (!$article) {
            throw $this->createNotFoundException();
        }
        $this->get('bns_app_blog.blog_manager')->canEditArticle($article);
		// Only teachers can delete articles
		$canManage = $this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION')
			|| ($this->get('bns.right_manager')->hasRight('BLOG_PUBLISH') && $this->getUser()->getId() === $article->getAuthorId());
		if (!$article->isDraft() && !$canManage) {
			throw new AccessDeniedHttpException('You can NOT delete this article with your permission !');
		}

		// Process
		$article->delete();

		if (!$this->getRequest()->isXmlHttpRequest()) {
			$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans("ARTICLE_DELETED", array(), "BLOG"));

			return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
		}

		return new Response();
	}

    /**
     * @Route("/blog/commentaires/moderation", name="blog_manager_moderation_switch")
     * @Rights("BLOG_ADMINISTRATION")
     */
    public function switchModerationAction(Request $request)
    {
        $context = $this->get('bns.right_manager')->getContext();
        /** @var Blog $blog */
        $blog = BlogQuery::create()
            ->filterByGroupId($context['id'])
            ->findOne()
        ;

        if (!$blog) {
            throw new NotFoundHttpException('The blog with group id ' . $context['id'] . ' is NOT found !');
        }

        $state = json_decode($request->getContent());

        if ($state && isset($state->state)) {
            $blog->setIsCommentModerate(!$state->state);
            $blog->save();
        }

        return new JsonResponse(array('moderate' => $blog->getIsCommentModerate()));
    }

    protected function getCurrentBlog()
    {
        if (!isset($this->currentBlog)) {
            $this->currentBlog = $this->get('bns.right_manager')->getCurrentGroup()->getBlog();
        }

        return $this->currentBlog;
    }
}
