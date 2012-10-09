<?php

namespace BNS\App\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
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
class BackAjaxController extends Controller
{
	/**
	 * @Route("/brouillon/sauvegarder", name="blog_manager_draft_save")
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function saveDraftAction()
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$params = $this->getRequest()->get('blog_article_form');
			
			if (isset($params['id']) && null != $params['id']) {
				$article = BlogArticleQuery::create()
					->join('Blog')
					->add(BlogPeer::GROUP_ID, $context['id'])
				->findPk($params['id']);
				
				if (null == $article) {
					$article = new BlogArticle();
				}
			}
			else {
				$article = new BlogArticle();
			}
			
			$form = $this->createForm(new BlogArticleType(), $article);
			$form->bindRequest($this->getRequest());
			
			if ($form->isValid()) {
				$article = $form->getData();
				$article->setUpdatedAt(time());
				$article->setStatus(BlogArticlePeer::STATUS_DRAFT);
				$article->setIsStar(false); // always false when is draft
				
				// Is new ?
				if (null == $article->getCreatedAt()) {
					$article->setCreatedAt(time());
					$article->setBlogId($context['id']);
					$article->setAuthorId($this->getUser()->getId());
				}
				
				// Finally
				$article->save();
				
				return new Response(json_encode(array(
					'response'	=> true,
					'articleId'	=> $article->getId()
				)));
			}
			else {
				$errorsArray = array();
				foreach ($form->getChildren() as $children) {
					if (count($children->getErrors()) > 0) {
						foreach ($children->getErrors() as $error) {
							$errorsArray[] = $error->getMessage();
						}
					}
				}
				
				if (count($errorsArray) > 1) {
					$errors = '<ul>';
					foreach ($errorsArray as $error) {
						$errors .= '<li>' . $error . '</li>';
					}
					$errors = '</ul>';
				}
				else {
					$errors = $errorsArray[0];
				}
				
				return new Response(json_encode(array(
					'errors' => $errors
				)));
			}
		}
		
		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}
	
	/**
	 * @Route("/article/{articleSlug}/epingler", name="blog_manager_article_pin")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function starAction($articleSlug)
	{
		if ($this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$article = BlogArticleQuery::create('ba')
				->join('Blog')
				->where('Blog.GroupId = ?', $context['id'])
				->where('ba.Slug = ?', $articleSlug)
			->findOne();
			
			if (null == $article) {
				throw new NotFoundHttpException('The article with slug : ' . $articleSlug . ' is not found !');
			}
			
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
	public function addCategoryAction($isManage = false)
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			if (!$this->getRequest()->get('title', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}
			
			$context = $this->get('bns.right_manager')->getContext();
			$blog = BlogQuery::create()
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findOne();
			
			if (null == $blog) {
				throw new NotFoundHttpException('The blog with group id ' . $context['id'] . ' is NOT found !');
			}
			
			$rootCategory = BlogCategoryQuery::create()->findRoot($blog->getId());
			$blogCategory = new BlogCategory();
			$blogCategory->setBlogId($context['id']);
			$blogCategory->setTitle($this->getRequest()->get('title'));
			$blogCategory->insertAsFirstChildOf($rootCategory);
			
			if (null != $this->getRequest()->get('iconName', null)) {
				$blogCategory->setIconClassname($this->getRequest()->get('iconName'));
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
	public function addCategoryManageAction()
	{
		return $this->addCategoryAction(true);
	}
	
	/**
	 * @Route("/category/save", name="blog_manager_category_save", options={"expose"=true})
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function saveCategoriesAction()
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			if ($this->getRequest()->get('categories', false) === false) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}
			
			$context = $this->get('bns.right_manager')->getContext();
			$categories = $this->getRequest()->get('categories');
			
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
			
			$dump = array();
			foreach ($categories as $parentCat) {
				$pCat = $blogCatById[$parentCat['id']];
				$pCat->moveToLastChildOf($root);
				$dump[$parentCat['id']] = null;
				
				if (isset($parentCat['children'])) {
					$dump[$parentCat['id']] = array();
					foreach ($parentCat['children'] as $subCategory) {
						$sCat = $blogCatById[$subCategory['id']];
						$sCat->moveToLastChildOf($pCat);
						$sCat->save();
						$dump[$parentCat['id']][$subCategory['id']] = null;
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
	public function editCategoryAction()
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			if (!$this->getRequest()->get('title', false) || !$this->getRequest()->get('id', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}
			
			$categoryId = $this->getRequest()->get('id');
			$context = $this->get('bns.right_manager')->getContext();
			$category = BlogCategoryQuery::create()
				->join('Blog')
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findPk($categoryId);
			
			if (null == $category) {
				throw new NotFoundHttpException('The category with id : ' . $categoryId . ' is not found !');
			}
			
			$category->setTitle($this->getRequest()->get('title'));
			if (false !== $this->getRequest()->get('iconName', false)) {
				$category->setIconClassname($this->getRequest()->get('iconName'));
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
	public function deleteCategoryAction()
	{
		if ($this->getRequest()->isXmlHttpRequest()) {
			if (!$this->getRequest()->get('id', false)) {
				throw new InvalidArgumentException('There is one missing mandatory field !');
			}
			
			$categoryId = $this->getRequest()->get('id');
			$context = $this->get('bns.right_manager')->getContext();
			$category = BlogCategoryQuery::create()
				->join('Blog')
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findPk($categoryId);
			
			if (null == $category) {
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
			->add(BlogPeer::GROUP_ID, $context['id'])
			->add(BlogArticlePeer::ID, $articleId)
		->findOne();
		
		if (null == $article) {
			throw new NotFoundHttpException('The article with id : ' . $articleId . ' is NOT found !');
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
	 * @Route("/article/{articleId}/supprimer", name="blog_manager_article_delete")
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function deleteArticleAction($articleId)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$query = BlogArticleQuery::create('ba')
			->join('Blog')
			->where('Blog.GroupId = ?', $context['id'])
			->where('ba.Id = ?', $articleId)
		;
		
		// A child can only delete his own article
		if ($this->get('bns.right_manager')->isChild()) {
			$query->where('ba.AuthorId = ?', $this->getUser()->getId());
		}
		
		$article = $query->findOne();
		
		if (null == $article) {
			throw new NotFoundHttpException('The article with id : ' . $articleId . ' is NOT found !');
		}
		
		// Only teachers can delete articles
		if (!$article->isDraft() && !$this->get('bns.right_manager')->hasRight('BLOG_ADMINISTRATION')) {
			throw new AccessDeniedHttpException('You can NOT delete this article with your permission !');
		}
		
		// Process
		$article->delete();
		
		if (!$this->getRequest()->isXmlHttpRequest()) {
			$this->get('session')->getFlashBag()->add('success', "L'article a été supprimé avec succès !");
			
			return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
		}
		
		return new Response();
	}
	
	/**
	 * @Route("/blog/commentaires/moderation", name="blog_manager_moderation_switch")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function switchModerationAction()
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$blog = BlogQuery::create()
			->add(BlogPeer::GROUP_ID, $context['id'])
		->findOne();

		if (null == $blog) {
			throw new NotFoundHttpException('The blog with group id ' . $context['id'] . ' is NOT found !');
		}
		
		$blog->switchIsCommentModerate();
		$blog->save();
		
		return new Response();
	}
}