<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Access\BNSAccess;
use \BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Events\BnsEvents;
use BNS\App\CoreBundle\Events\ThumbnailRefreshEvent;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSitePageType;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSiteType;
use \BNS\App\MiniSiteBundle\Model\MiniSitePage;
use \BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidget;
use \BNS\App\MiniSiteBundle\Model\MiniSiteWidgetTemplateQuery;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Component\DependencyInjection\Container;
use \Symfony\Component\HttpFoundation\Response;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * @Route("/gestion/personnalisation")
 */
class BackCustomController extends AbstractMiniSiteController
{
	/**
	 * @Route("/", name="minisite_manager_custom")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function indexAction()
	{
		$miniSite = $this->getMiniSite();
		$page = new MiniSitePage();
		$page->setType('TEXT');
		$newPageForm = $this->createForm(new MiniSitePageType(), $page);

		return $this->render('BNSAppMiniSiteBundle:Custom:index.html.twig', array(
			'minisite'	  => $miniSite,
			'newPageForm' => $newPageForm->createView()
		));
	}

	/**
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSitePage $page
	 *
	 * @return Response
	 */
	public function renderPageAction(MiniSitePage $page)
	{
		$form = $this->createForm(new MiniSitePageType(), $page, array(
			'page' => $page,
			'is_edition' => true
		));

		return $this->render('BNSAppMiniSiteBundle:Page:back_page_row.html.twig', array(
			'page' => $page,
			'form' => $form->createView()
		));
	}

	/**
	 * @Route("/editeurs", name="minisite_manager_custom_editors")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function editorsAction()
	{
		$miniSite = $this->getMiniSite();

		return $this->render('BNSAppMiniSiteBundle:Custom:editors.html.twig', array(
			'minisite' => $miniSite,
		));
	}

	/**
	 * @Route("/widgets", name="minisite_manager_custom_widgets")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function widgetsAction()
	{
	    if (!$this->hasFeature('minisite_widgets')) {
	        throw $this->createAccessDeniedException();
        }
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create('ms')
			->joinWith('ms.MiniSitePage msp')
			->joinWith('ms.MiniSiteWidget msw', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetTemplate mswt', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetExtraProperty mswep', \Criteria::LEFT_JOIN)
			->where('ms.GroupId = ?', $context['id'])
			->orderBy('msp.Rank')
			->orderBy('msw.Rank')
		);

		// Convert widget into class
		$widgets = array();
		$usedTemplates = array();

		foreach ($miniSite->getMiniSiteWidgets() as $widget) {
			$className = '\\BNS\\App\\MiniSiteBundle\\Widget\\MiniSiteWidget' . ucfirst(Container::camelize(strtolower($widget->getMiniSiteWidgetTemplate()->getType())));
			$widgets[] = $className::create($widget);
			$usedTemplates[] = $widget->getWidgetTemplateId();
		}

		$widgetTemplates = MiniSiteWidgetTemplateQuery::create('mswt')
		->find();

		return $this->render('BNSAppMiniSiteBundle:Custom:widgets.html.twig', array(
			'minisite'		=> $miniSite,
			'widgets'		=> $widgets,
			'templates'		=> $widgetTemplates,
			'usedTemplates' => $usedTemplates,
			'csrf_token'	=> $this->container->get('form.csrf_provider')->generateCsrfToken('unknown')
		));
	}

	/**
	 * @Route("/informations", name="minisite_manager_custom_informations")
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function informationsAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite();

		$form = $this->createForm(new MiniSiteType(), $miniSite);
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bind($this->getRequest());
			if ($form->isValid()) {
				$miniSite = $form->getData();
				$miniSite->save();

                $dispatcher = $this->get('event_dispatcher');
                $dispatcher->dispatch(BnsEvents::THUMB_REFRESH, new ThumbnailRefreshEvent($miniSite, 'small'));
				// Redirect to avoid refresh
				return $this->redirect($this->generateUrl('minisite_manager_custom_informations'));
			}
		}

		return $this->render('BNSAppMiniSiteBundle:Custom:informations.html.twig', array(
			'minisite'	=> $miniSite,
			'form'		=> $form->createView()
		));
	}

	/**
	 * @param MiniSiteWidget $widget
	 * @param string $view
	 *
	 * @return Response
	 */
	public function renderWidgetAction($widget, $view)
	{
		return $this->render($view, array(
			'widget'		=> $widget,
			'form'			=> $this->createForm($widget->getFormType(), $widget)->createView()
		));
	}

	/**
	 * @param string $view
	 * @param array $parameters
	 * @param \BNS\App\MiniSiteBundle\Controller\Response $response
	 */
	public function render($view, array $parameters = array(), Response $response = null)
	{
		// Inject parameter for sidebar custom block
		$parameters['isCustomRoute'] = true;

		return parent::render($view, $parameters, $response);
	}
}
