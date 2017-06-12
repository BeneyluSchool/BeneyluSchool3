<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSitePageType;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageEditor;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageEditorQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidget;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidgetQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidgetTemplatePeer;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidgetTemplateQuery;

use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\DependencyInjection\Container;
use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/gestion")
 */
class BackAjaxController extends AbstractMiniSiteController
{    
	/**
	 * @Route("/personnalisation/pages/ordre", name="minisite_manager_custom_page_order")
	 * 
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
	 * 
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
	 * @Route("/personnalisation/page/{id}/editer", name="minisite_manager_custom_page_edit", options={"expose": true})
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function editPageAction($id)
	{
		$request = $this->getRequest();
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest()) {
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

		$form = $this->createForm(new MiniSitePageType(), clone $page, array(
			'is_edition' => true
		));
		$form->bind($this->getRequest());

		$errors = array();
		if ($form->isValid()) {
			$page = $form->getData();

			if (!$page->isActivated() && $page->isHome()) {
				$page->setIsHome(false);
			}
			
			$homePage = $miniSite->getHomePage();
			if (null == $homePage) {
				throw new \RuntimeException('Can NOT find the minisite homepage for id ' . $miniSite->getId());
			}

			if ($page->isHome() && $homePage->getId() != $page->getId()) {
				$homePage->setIsHome(false);
				$homePage->save();
			}
			elseif ($homePage->getId() == $page->getId()) {
				// You can NOT remove the home status on the homepage !
				$page->setIsHome(true);
			}
			
			$page->save();
		}
		
		return new Response(json_encode(array(
			'has_errors' => isset($errors[0]),
			'errors'	 => $errors,
			'is_home'	 => $form->getData()->isHome()
		)));
	}
	
	/**
	 * @Route("/personnalisation/widget/{widgetId}/sauvegarder", name="minisite_manager_custom_widget_save", options={"expose"=true})
	 * 
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
		$form->bind($this->getRequest());
		$errors = false;
		
		if ($form->isValid()) {
			$widgetData = $form->getData();
			$widgetData->save();
		}
		else {
			$errorsAsObject = $form->getErrors();
			$errors = array();

			foreach ($errorsAsObject as $error) {
				$errors[] = $error->getMessage();
			}
		}
		
		return new Response(json_encode(array('errors' => $errors)));
	}
	
		
	/**
	 * @Route("/personnalisation/widget/{widgetType}/nouveau", name="minisite_manager_custom_widget_new", options={"expose"=true})
	 * 
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
	 * @Route("/personnalisation/page/ajouter", name="minisite_manager_custom_page_new")
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function newPageAction(Request $request)
	{
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be POST & AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		$form = $this->createForm(new MiniSitePageType());
		$form->bind($request);

		if ($form->isValid()) {
			$page = $form->getData();
			$page->setMiniSiteId($miniSite->getId());
			$page->save();
            
            //statistic action
            //type égale à 1 si page dynamique, égale à 0 si statique 
            if('NEWS' == $page->getType()) {
                $this->get("stat.site")->createDynamicPage();
            } else if('TEXT' == $page->getType()) {
                $this->get("stat.site")->createStaticPage();
            }
		}
        
		return $this->forward('BNSAppMiniSiteBundle:BackCustom:renderPage', array(
			'page'	=> $page
		));
	}
	
	/**
	 * @Route("/personnalisation/page/supprimer", name="minisite_manager_custom_page_delete")
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deletePageAction(Request $request)
	{
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
			$request->get('id', false) === false) {
			throw new NotFoundHttpException('The page request must be POST with mandatory parameters & AJAX !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($request->get('id'));
		
		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $request->get('id') . ' is NOT found !');
		}

		// Can NOT delete homepage
		if ($page->isHome()) {
			throw new \RuntimeException('Can NOT delete minisite homepage !');
		}

		// Process
		$page->delete();

		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/widget/supprimer", name="minisite_manager_custom_widget_delete")
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deleteWidgetAction(Request $request)
	{
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
			$request->get('widget_id', false) === false) {
			throw new NotFoundHttpException('The page request must be POST with mandatory parameters & AJAX !');
		}
		
		$context = $this->get('bns.right_manager')->getContext();
		$widget = MiniSiteWidgetQuery::create('msw')
			->joinWith('msw.MiniSite ms')
			->where('ms.GroupId = ?', $context['id'])
			->where('msw.Id = ?', $request->get('widget_id'))
		->findOne();
		
		if (null == $widget) {
			throw new NotFoundHttpException('The widget with id : ' . $request->get('widget_id') . ' is NOT found !');
		}
		
		// Process
		$widget->delete();
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/page/switch", name="minisite_manager_switch_activation_page")
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function switchPageActivationAction()
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

		// Can NOT disable homepage
		if ($page->isHome()) {
			throw new \RuntimeException('Can NOT disable homepage id ' . $page->getId() . ' !');
		}
		
		$page->switchActivation();
		$page->save();
		
		return new Response();
	}
	
	/**
	 * @Route("/personnalisation/page/confidentialite", name="minisite_manager_page_confidentiality")
	 * 
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function switchPageConfidentialityAction(Request $request)
	{
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
			$request->get('id', false) === false) {
			throw new NotFoundHttpException('The page request must be POST with mandatory parameters & AJAX !');
		}
		
		if (!$this->get('bns.group_manager')->setGroup($this->get('bns.right_manager')->getCurrentGroup())->getAttribute('MINISITE_ALLOW_PUBLIC', false)) {
			throw new AccessDeniedHttpException('The environment does NOT allow to switch your minisite public status !');
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($request->get('id'));
		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $request->get('id') . ' is NOT found !');
		}
		
		$page->switchConfidentiality();
		$page->save();
		
		return new Response();
	}

	/**
	 * @Route("/personnalisation/editeurs/ajouter", name="minisite_manager_editors_add")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function addEditors(Request $request)
	{
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
			$request->get('page_id', false) === false || $request->get('editors_ids', false) === false) {
			throw new NotFoundHttpException('The page request must be POST with mandatory parameters & AJAX !');
		}

		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($request->get('page_id'));

		if (false === $page) {
			throw new NotFoundHttpException('The page with id : ' . $request->get('page_id') . ' is NOT found !');
		}

		$classRoomManager = $this->get('bns.classroom_manager');
		$classRoomManager->setClassroom($this->get('bns.right_manager')->getCurrentGroup());

		$editorsIds = $request->get('editors_ids');
		$foundEditors = array();
		$pupils = $classRoomManager->getPupils();

		// Check if it's my user
		foreach ($editorsIds as $editor) {
			foreach ($pupils as $pupil) {
				if ($pupil->getId() == $editor) {
					$foundEditors[] = $pupil;
					break 1;
				}
			}
		}

		if (count($foundEditors) != count($editorsIds)) {
			$teachers = $classRoomManager->getTeachers();

			foreach ($editorsIds as $editor) {
				foreach ($teachers as $teacher) {
					if ($teacher->getId() == $editor) {
						$foundEditors[] = $teacher;
						break 1;
					}
				}
			}
		}

		// Saving process
		foreach ($foundEditors as $user) {
			$editor = new MiniSitePageEditor();
			$editor->setPageId($page->getId());
			$editor->setUserId($user->getId());
			$editor->save();
		}

		return $this->render('BNSAppMiniSiteBundle:Editor:editor_list.html.twig', array(
			'editors' => $foundEditors
		));
	}

	/**
	 * @Route("/personnalisation/editeurs/supprimer", name="minisite_manager_editors_delete")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function deleteEditor(Request $request)
	{
		$editorSlug = $request->get('editor_slug', false);
		$editorId = $request->get('editor_id', false);
		$pageId = $request->get('page_id', false);
		if (!$request->isMethod('POST') || !$request->isXmlHttpRequest() ||
			($editorSlug === false && $editorId === false) || $pageId === false) {
			throw new NotFoundHttpException('The page request must be POST with mandatory parameters & AJAX !');
		}

		$editor = MiniSitePageEditorQuery::create('mspe')
			->join('mspe.MiniSitePage msp')
			->join('mspe.User u')
			->where('mspe.PageId = ?', $pageId)
			->_if($editorSlug)
				->where('u.Slug = ?', $editorSlug)
			->_else()
				->where('u.Id = ?', $editorId)
			->_endif()
		->findOne();

		// Editor not found
		if (null == $editor) {
			return $this->redirectHome();
		}
		
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageById($request->get('page_id'));

		// Foreign page
		if (false === $page) {
			return $this->redirectHome();
		}

		// Finally
		$editor->delete();

		return new Response();
	}
}
