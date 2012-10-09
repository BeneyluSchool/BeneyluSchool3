<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Container;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Form\Type\MiniSitePageType;
use BNS\App\CoreBundle\Model\MiniSiteWidgetQuery;
use BNS\App\CoreBundle\Model\MiniSiteWidget;
use BNS\App\CoreBundle\Model\MiniSitePage;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSiteWidgetTemplateQuery;
use BNS\App\CoreBundle\Model\MiniSiteWidgetTemplatePeer;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\MiniSiteWidgetPeer;
use BNS\App\CoreBundle\Model\MiniSitePeer;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\CoreBundle\Model\MiniSitePageNewsQuery;
use BNS\App\CoreBundle\Model\MiniSitePageQuery;

/**
 * @Route("/gestion")
 */
class BackAjaxController extends AbstractMiniSiteController
{
	/**
	 * @Route("/personnalisation/pages/ordre", name="minisite_manager_custom_page_order")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function savePageOrderAction()
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$pages = $this->getRequest()->get('pages', null);
		if (null == $pages) {
			throw new \InvalidArgumentException('The parameter "pages" is missing !');
		}
		
		$miniSite = $this->getMiniSite();
		$miniSitePages = array();
		
		foreach ($miniSite->getMiniSitePages() as $miniSitePage) {
			$miniSitePages[$miniSitePage->getId()] = $miniSitePage;
		}
		
		foreach ($pages as $rank => $page) {
			$id = substr(strrchr($page, '_'), 1);
			$miniSitePages[$id]->setRank($rank + 1);
			$miniSitePages[$id]->save();
		}
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/widget/ordre", name="minisite_manager_custom_widget_order")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function saveWidgetOrderAction()
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$widgets = $this->getRequest()->get('widgets', null);
		if (null == $widgets) {
			throw new \InvalidArgumentException('The parameter "widgets" is missing !');
		}
		
		$miniSite = $this->getMiniSite();
		$miniSiteWidgets = array();
		
		foreach ($miniSite->getMiniSiteWidgets() as $miniSiteWidget) {
			$miniSiteWidgets[$miniSiteWidget->getId()] = $miniSiteWidget;
		}
		
		foreach ($widgets as $rank => $widget) {
			$id = substr(strrchr($widget, '-'), 1);
			$miniSiteWidgets[$id]->setRank($rank + 1);
			$miniSiteWidgets[$id]->save();
		}
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/page/{id}/sauvegarder", name="minisite_manager_custom_page_save")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function savePageAction($id)
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($id);
		
		if ($page === false) {
			throw new AccessDeniedHttpException('You try to edit a page from a foreign minisite !');
		}
		
		if (null == $page) {
			throw new NotFoundHttpException('The page with id ' . $id . ' is NOT found !');
		}
		
		$form = $this->createForm(new MiniSitePageType(), $page);
		$form->bindRequest($this->getRequest());
		
		$errors = array();
		if ($form->isValid()) {
			$page = $form->getData();
			
			if (!$page->isActivated() && $page->isHome()) {
				$page->setIsHome(false);
			}
			
			$context = $this->get('bns.right_manager')->getContext();
			$homePage = MiniSitePageQuery::create()
				->join('MiniSite')
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->add(MiniSitePagePeer::IS_HOME, true)
			->findOne();
			
			if (null != $homePage && $page->isHome() && $homePage->getId() != $page->getId()) {
				$homePage->setIsHome(false);
				$homePage->save();
			}
			else if ($homePage->getId() == $page->getId() && !$page->isHome()) {
				throw new AccessDeniedHttpException('You can NOT remove the home status on the homepage !');
			}
			
			$page->save();
		}
		else {
			foreach ($form->getErrors() as $error) {
				$errors[] = $error->getMessage();
			}
		}
		
		return new Response(json_encode(array(
			'errors'	=> $errors
		)));
	}
	
	/**
	 * @Route("/personnalisation/widget/{widgetId}/sauvegarder", name="minisite_manager_custom_widget_save", options={"expose"=true})
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function saveWidgetAction($widgetId)
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$abstractWidget = MiniSiteWidgetQuery::create()
			->joinWith('MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidgetTemplate')
		->findPK($widgetId);
		
		if (null == $abstractWidget) {
			throw new NotFoundHttpException('The widget with id : ' . $widgetId . ' is NOT found !');
		}
		
		$className = '\\BNS\\App\\MiniSiteBundle\\Widget\\MiniSiteWidget' . ucfirst(Container::camelize(strtolower($abstractWidget->getMiniSiteWidgetTemplate()->getType())));
		$widget = $className::create($abstractWidget);
		
		$form = $this->createForm($widget->getFormType(), $widget);
		$form->bindRequest($this->getRequest());
		if ($form->isValid()) {
			$widgetData = $form->getData();
			$widgetData->save();
			
			return new Response();
		}
		
		$errors = $form->getErrorsAsString();
		
		return new Response(json_encode(array('errors' => $errors)));
	}
	
		
	/**
	 * @Route("/personnalisation/widget/{widgetType}/nouveau", name="minisite_manager_custom_widget_new", options={"expose"=true})
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function newWidgetAction($widgetType)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$widgetTemplate = MiniSiteWidgetTemplateQuery::create()
			->add(MiniSiteWidgetTemplatePeer::TYPE, $widgetType)
		->findOne();
		
		if (null == $widgetTemplate) {
			throw new NotFoundHttpException('The widget template with type : ' . $widgetType . ' is NOT found !');
		}
		
		$miniSite = $this->getMiniSite();
		
		$widgetClass = new MiniSiteWidget();
		$widgetClass->setMiniSiteWidgetTemplate($widgetTemplate);
		$widgetClass->setMiniSiteId($miniSite->getId());
		$widgetClass->insertAtTop();
		$widgetClass->save();
		
		$className = '\\BNS\\App\\MiniSiteBundle\\Widget\\MiniSiteWidget' . ucfirst(Container::camelize(strtolower($widgetType)));
		$widget = $className::create($widgetClass);
		
		return $this->render($widget->getViewPath(true), array(
			'widget'	=> $widget,
			'form'		=> $this->createForm($widget->getFormType(), $widget)->createView()
		));
	}
	
	/**
	 * @Route("/page/ajouter", name="minisite_manager_page_add")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function addPageAction()
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		
		$page = new MiniSitePage();
		$page->setTitle('Nouvelle Page');
		$page->setMiniSiteId($miniSite->getId());
		$page->setType(MiniSitePagePeer::TYPE_TEXT);
		$page->setIsActivated(false);
		$page->save();
		
		return $this->render('BNSAppMiniSiteBundle:Page:back_page_row.html.twig', array(
			'page'	=> $page,
			'isNew'	=> true
		));
	}
	
	/**
	 * @Route("/personnalisation/page/{pageId}/supprimer/confirmation/", name="minisite_manager_custom_page_delete_confirm")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function showDeleteModalPageAction($pageId)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($pageId);
		
		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $pageId . ' is NOT found !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'page' => $page,
			),
			'footerValues'	=> array(
				'page'  => $page,
				'route'	=> $this->generateUrl('minisite_manager_custom_page_delete', array('pageId' => $page->getId()))
			),
			'type'	=> 'page',
			'title'	=> $page->getTitle()
		));
	}
	
	/**
	 * @Route("/personnalisation/page/{pageId}/supprimer", name="minisite_manager_custom_page_delete")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deletePageAction($pageId)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($pageId);
		
		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $pageId . ' is NOT found !');
		}
		
		// Process
		$page->delete();
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/widget/{widgetId}/supprimer/confirmation/", name="minisite_manager_custom_widget_delete_confirm")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function showDeleteModalWidgetAction($widgetId)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$widget = MiniSiteWidgetQuery::create()
			->joinWith('MiniSite')
			->add(MiniSitePeer::GROUP_ID, $context['id'])
			->add(MiniSiteWidgetPeer::ID, $widgetId)
		->findOne();
		
		if (null == $widget) {
			throw new NotFoundHttpException('The widget with id : ' . $widgetId . ' is NOT found !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'widget' => $widget,
			),
			'footerValues'	=> array(
				'widget' => $widget,
				'route'	 => $this->generateUrl('minisite_manager_custom_widget_delete', array('widgetId' => $widget->getId()))
			),
			'type'	=> 'widget',
			'title'	=> $widget->getTitle()
		));
	}
	
	/**
	 * @Route("/personnalisation/widget/{widgetId}/supprimer", name="minisite_manager_custom_widget_delete")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deleteWidgetAction($widgetId)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$widget = MiniSiteWidgetQuery::create()
			->joinWith('MiniSite')
			->add(MiniSitePeer::GROUP_ID, $context['id'])
			->add(MiniSiteWidgetPeer::ID, $widgetId)
		->findOne();
		
		if (null == $widget) {
			throw new NotFoundHttpException('The widget with id : ' . $widgetId . ' is NOT found !');
		}
		
		// Process
		$widget->delete();
		
		return new Response();
	}
	
	/**
	 * @Route("/page/news/{slug}/supprimer/confirmation/", name="minisite_manager_page_news_delete_confirm")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function showDeleteModalPageNewsAction($slug)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$news = MiniSitePageNewsQuery::create()
			->joinWith('MiniSitePage')
			->joinWith('MiniSitePage.MiniSite')
			->add(MiniSitePeer::GROUP_ID, $context['id'])
			->add(MiniSitePageNewsPeer::SLUG, $slug)
		->findOne();
		
		if (null == $news) {
			throw new NotFoundHttpException('The widget with slug : ' . $slug . ' is NOT found !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'news' => $news,
			),
			'footerValues'	=> array(
				'news' => $news,
				'route'	 => $this->generateUrl('minisite_manager_page_news_delete', array('slug' => $news->getSlug()))
			),
			'type'	=> 'news',
			'title'	=> $news->getTitle()
		));
	}
	
	/**
	 * @Route("/page/news/{slug}/supprimer", name="minisite_manager_page_news_delete")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deletePageNewsAction($slug)
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$news = MiniSitePageNewsQuery::create()
			->joinWith('MiniSitePage')
			->joinWith('MiniSitePage.MiniSite')
			->add(MiniSitePeer::GROUP_ID, $context['id'])
			->add(MiniSitePageNewsPeer::SLUG, $slug)
		->findOne();
		
		if (null == $news) {
			throw new NotFoundHttpException('The widget with slug : ' . $slug . ' is NOT found !');
		}
		
		// Process
		$news->delete();
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/editeur/supprimer", name="minisite_manager_editor_delete", options={"expose"=true})
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deleteEditorAction()
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$editorId = $this->getRequest()->get('editor_id', null);
		if (null == $editorId) {
			throw new \InvalidArgumentException('The parameter editor_id is missing !');
		}
		
		$user = UserQuery::create()->findPK($editorId);
		if (null == $user) {
			throw new NotFoundHttpException('The editor user with id : ' . $editorId . ' is NOT found !');
		}
		
		$this->getEditorSubGroupManager()->removeUser($user);
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/page/switch", name="minisite_manager_switch_activation_page")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function switchActivationPageAction()
	{
		if ('POST' != $this->getRequest()->getMethod() || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$pageId = $this->getRequest()->get('page_id', null);
		if (null == $pageId) {
			throw new \InvalidArgumentException('The parameter page_id is missing !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($pageId);
		
		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $pageId . ' is NOT found !');
		}
		
		$page->switchActivation();
		$page->save();
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/public/switch", name="minisite_manager_switch_public")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function switchPublicAction()
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}
		
		if (!$this->get('bns.group_manager')->getAttribute('MINISITE_ALLOW_PUBLIC', false)) {
			throw new AccessDeniedHttpException('The environment does NOT allow to switch your minisite public status !');
		}
		
		$miniSite = $this->getMiniSite();
		$miniSite->switchPublic();
		$miniSite->save();
		
		return new Response();
	}
}