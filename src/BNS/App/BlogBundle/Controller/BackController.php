<?php

namespace BNS\App\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogCategoryPeer;
use BNS\App\CoreBundle\Model\BlogArticleQuery;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\BlogBundle\Form\Type\BlogArticleType;
use BNS\App\CoreBundle\Model\BlogPeer;
use BNS\App\BlogBundle\Form\Type\BlogType;
use BNS\App\BlogBundle\Form\Model\BlogArticleFormModel;
use BNS\App\CoreBundle\Utils\Crypt;

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
    public function indexAction()
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
		
		// Gestion des filtres d'articles
		$this->getRequest()->getSession()->remove('blog_articles_filters');
		
        return $this->render('BNSAppBlogBundle:Back:index_manager.html.twig', array(
			'blog' => $blogs[0]
		));
    }
	
	/**
	 * @Route("/nouvel-article", name="blog_manager_new_article")
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function newArticleAction()
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
		
		$form = $this->createForm(new BlogArticleType(), new BlogArticleFormModel());
		
		return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
			'blog'			=> $blogs[0],
			'form'			=> $form->createView(),
			'article'		=> $form->getData()->getArticle(),
			'isEditionMode'	=> false
		));
	}
	
	/**
	 * @Route("/nouvel-article/terminer", name="blog_manager_new_article_finish")
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function finishNewArticleAction()
	{
		if ('POST' == $this->getRequest()->getMethod()) {
			$context = $this->get('bns.right_manager')->getContext();
			$form = $this->createForm(new BlogArticleType(), new BlogArticleFormModel());
			$form->bindRequest($this->getRequest());
			
			$blog = BlogQuery::create()
				->add(BlogPeer::GROUP_ID, $context['id'])
			->findOne();

			if (null == $blog) {
				throw new NotFoundHttpException('The blog with the group id : ' . $context['id']  . ' is NOT found ! ');
			}
			
			if ($form->isValid()) {
				$article = $form->getData();
				
				// Finally
				$article->save($this->get('bns.right_manager'), $this->getUser(), $this->get('bns.resource_manager'), $this->getRequest(), $blog);
			}
			else {
				$blogs = BlogQuery::create()
					->joinWith('BlogCategory', \Criteria::LEFT_JOIN)
					->add(BlogPeer::GROUP_ID, $context['id'])
					->addAscendingOrderByColumn(BlogCategoryPeer::LEFT)
				->find(); // Automatic ORM join, do NOT use findOneBy
				if (!isset($blogs[0])) {
					throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
				}
				
				return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
					'blog'			=> $blogs[0],
					'form'			=> $form->createView(),
					'article'		=> $form->getData()->getArticle(),
					'isEditionMode'	=> false
				));
			}
		}
		
		return $this->redirect($this->generateUrl('BNSAppBlogBundle_back'));
	}
	
	/**
	 * @Route("/article/{articleSlug}/editer", name="blog_manager_edit_article", options={"expose"=true})
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function editArticleAction($articleSlug)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$articles = BlogArticleQuery::create()
			->joinWith('Blog')
			->joinWith('User')
			->joinWith('BlogArticleCategory', \Criteria::LEFT_JOIN)
			->joinWith('BlogArticleCategory.BlogCategory', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->add(BlogArticlePeer::SLUG, $articleSlug)
		->find();

		if (!isset($articles[0])) {
			throw new NotFoundHttpException('Article not found for slug : ' . $articleSlug . ' !');
		}
		
		$article = $articles[0];
		$isEditionMode = true;
		$form = $this->createForm(new BlogArticleType(), new BlogArticleFormModel($article));
		
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bindRequest($this->getRequest());
			if ($form->isValid()) {
				$model = $form->getData();
				
				// Finally
				$model->save($this->get('bns.right_manager'), $this->getUser(), $this->get('bns.resource_manager'), $this->getRequest());
				
				// Flash message
				$message = 'Ton article a été modifié avec succès ! Tu peux maintenant le voir.';
				if ($this->get('bns.right_manager')->isAdult()) {
					$message = 'Votre article a été modifié avec succès ! Vous pouvez maintenant le visualiser.';
				}
				$this->get('session')->getFlashBag()->add('success', $message);
				
				return $this->redirect($this->generateUrl('blog_manager_article_visualisation', array(
					'articleSlug' => $model->getArticle()->getSlug()
				)));
			}
		}
		
		$blogs = BlogQuery::create()
			->joinWith('BlogCategory', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->addAscendingOrderByColumn(BlogCategoryPeer::LEFT)
		->find(); // Automatic ORM join, do NOT use findOneBy
		
		if (!isset($blogs[0])) {
			throw new NotFoundHttpException('Blog not found for group id : ' . $context['id'] . ' !');
		}
		
		return $this->render('BNSAppBlogBundle:Back:article_form.html.twig', array(
			'blog'			=> $blogs[0],
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
	public function loadCategoriesBlockAction(Blog $blog, $article = null)
	{
		return $this->render('BNSAppBlogBundle:Block:back_block_categories.html.twig', array(
			'blog'			=> $blog,
			'isEditionMode'	=> null != $article && !$article->isNew(),
			'article'		=> $article
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
	 * @Rights("BLOG_ACCESS_BACK")
	 */
	public function customAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
        $blog = BlogQuery::create()
			->joinWith('Resource', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
		->findOne();
		
		if (null == $blog) {
			throw new NotFoundHttpException('The blog with the group id : ' . $context['id']  . ' is NOT found ! ');
		}
		
		$form = $this->createForm(new BlogType(), $blog);
		
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bindRequest($this->getRequest());
			if ($form->isValid()) {
				$blog = $form->getData();
				$blog->save();
				
				$this->get('session')->getFlashBag()->add('success', 'La modification de votre blog a été effectuée avec succès !');
				
				// Redirect to avoid refresh
				return $this->redirect($this->generateUrl('blog_manager_custom'));
			}
		}
		
		return $this->render('BNSAppBlogBundle:Custom:index.html.twig', array(
			'blog'		=> $blog,
			'form'		=> $form->createView()
		));
	}
	
	/**
	 * @Route("/commentaires", name="blog_manager_comment")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function commentsAction($page = 1)
	{
		$context = $this->get('bns.right_manager')->getContext();
		 $blog = BlogQuery::create()
			->add(BlogPeer::GROUP_ID, $context['id'])
		->findOne();
		
		if (null == $blog) {
			throw new NotFoundHttpException('The blog with the group id : ' . $context['id']  . ' is NOT found ! ');
		}
		
		return $this->render('BNSAppBlogBundle:Comment:index.html.twig', array(
			'blog'		=> $blog,
			'namespace'	=> Crypt::encrypt('BNS\\App\\CoreBundle\\Model\\BlogArticleComment'),
			'page'		=> $page
		));
	}
	
	/**
	 * @Route("/categories", name="blog_manager_categories")
	 * @Rights("BLOG_ADMINISTRATION")
	 */
	public function categoriesAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		 $blogs = BlogQuery::create()
			->joinWith('BlogCategory', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->addAscendingOrderByColumn(BlogCategoryPeer::LEFT)
		->find();
		
		if (!isset($blogs[0])) {
			throw new NotFoundHttpException('The blog with the group id : ' . $context['id']  . ' is NOT found ! ');
		}
		
		return $this->render('BNSAppBlogBundle:Custom:categories.html.twig', array(
			'blog' => $blogs[0]
		));
	}
	
	/**
	 * @Route("/visualisation/{articleSlug}", name="blog_manager_article_visualisation")
	 * 
	 * @param string $articleSlug
	 */
	public function visualisationAction($articleSlug)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$articles = BlogArticleQuery::create()
			->joinWith('Blog')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource')
			->joinWith('BlogArticleCategory', \Criteria::LEFT_JOIN)
			->joinWith('BlogArticleCategory.BlogCategory', \Criteria::LEFT_JOIN)
			->add(BlogPeer::GROUP_ID, $context['id'])
			->add(BlogArticlePeer::SLUG, $articleSlug)
		->find();

		if (!isset($articles[0])) {
			throw new NotFoundHttpException('Article not found for slug : ' . $articleSlug . ' !');
		}
		
		$article = $articles[0];
		
		return $this->render('BNSAppBlogBundle:Article:back_article_visualisation.html.twig', array(
			'article'	=> $article
		));
	}
}
