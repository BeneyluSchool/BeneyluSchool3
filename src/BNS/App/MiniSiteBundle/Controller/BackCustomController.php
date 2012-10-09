<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSitePeer;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSiteWidgetPeer;
use BNS\App\CoreBundle\Model\MiniSiteQuery;
use BNS\App\CoreBundle\Model\MiniSiteWidgetTemplateQuery;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteType;
use BNS\App\CoreBundle\Model\MiniSiteWidget;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion/personnalisation")
 */
class BackCustomController extends AbstractMiniSiteController
{
	/**
	 * @Route("/", name="minisite_manager_custom")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function indexAction()
	{
		$miniSite = $this->getMiniSite();
		
		// Get all editor users
		$editors = $this->getEditorSubGroupManager()->getUsers(true);
		
		return $this->render('BNSAppMiniSiteBundle:Custom:index.html.twig', array(
			'minisite'				=> $miniSite,
			'editors'				=> $editors,
			'csrf_token'			=> $this->container->get('form.csrf_provider')->generateCsrfToken('unknown'),
			'switchPublicIsAllowed'	=> $this->get('bns.group_manager')->getAttribute('MINISITE_ALLOW_PUBLIC', false)
		));
	}
	
	/**
	 * @Route("/widgets", name="minisite_manager_custom_widgets")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function widgetsAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create()
			->joinWith('MiniSitePage')
			->joinWith('MiniSiteWidget', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidget.MiniSiteWidgetTemplate', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidget.MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
			->add(MiniSitePeer::GROUP_ID, $context['id'])
			->addAscendingOrderByColumn(MiniSitePagePeer::RANK)
			->addAscendingOrderByColumn(MiniSiteWidgetPeer::RANK)
		);
		
		// Convert widget into class
		$widgets = array();
		foreach ($miniSite->getMiniSiteWidgets() as $widget) {
			$className = '\\BNS\\App\\MiniSiteBundle\\Widget\\MiniSiteWidget' . ucfirst(Container::camelize(strtolower($widget->getMiniSiteWidgetTemplate()->getType())));
			$widgets[] = $className::create($widget);
		}
		
		$widgetTemplates = MiniSiteWidgetTemplateQuery::create()
			->joinWithI18n(BNSAccess::getLocale())
		->find();
		
		return $this->render('BNSAppMiniSiteBundle:Custom:widgets.html.twig', array(
			'minisite'		=> $miniSite,
			'widgets'		=> $widgets,
			'templates'		=> $widgetTemplates,
			'csrf_token'	=> $this->container->get('form.csrf_provider')->generateCsrfToken('unknown')
		));
	}
	
	/**
	 * @Route("/informations", name="minisite_manager_custom_informations")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function informationsAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create()
				->joinWith('MiniSitePage')
				->joinWith('MiniSitePage.MiniSitePageText', \Criteria::LEFT_JOIN)
				->joinWith('MiniSitePageText.User', \Criteria::LEFT_JOIN) // Author
				->joinWith('Resource', \Criteria::LEFT_JOIN)
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->addAscendingOrderByColumn(MiniSitePagePeer::RANK)
		);
		
		$form = $this->createForm(new MiniSiteType(), $miniSite);
		
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bindRequest($this->getRequest());
			if ($form->isValid()) {
				$miniSite = $form->getData();
				$miniSite->save();
				
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