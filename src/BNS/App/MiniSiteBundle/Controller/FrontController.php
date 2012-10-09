<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSiteQuery;
use BNS\App\CoreBundle\Model\MiniSitePeer;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSiteWidgetPeer;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontController extends AbstractMiniSiteController
{
	/**
	 * @Route("/", name="BNSAppMiniSiteBundle_front")
	 */
	public function indexAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create()
				->joinWith('MiniSitePage')
				->joinWith('MiniSitePage.MiniSitePageText', \Criteria::LEFT_JOIN)
				->joinWith('MiniSitePageText.User', \Criteria::LEFT_JOIN) // Author
				->joinWith('User.Profile', \Criteria::LEFT_JOIN)
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->joinWith('MiniSiteWidget', \Criteria::LEFT_JOIN)
				->joinWith('MiniSiteWidget.MiniSiteWidgetTemplate', \Criteria::LEFT_JOIN)
				->joinWith('MiniSiteWidget.MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
				->add(MiniSitePagePeer::IS_ACTIVATED, true)
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->addAscendingOrderByColumn(MiniSitePagePeer::RANK)
				->addAscendingOrderByColumn(MiniSiteWidgetPeer::RANK));
		
		if (!$this->isPublic()) {
			throw new NotFoundHttpException('This minisite is NOT public and you NOT have the right to see it !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
            'minisite'  => $miniSite,
			'page'		=> $this->getMiniSite()->getHomePage()
        ));
	}
	
	/**
	 * @Route("/{slug}", name="minisite_by_slug")
	 */
	public function bySlugAction($slug)
	{
		$miniSite = $this->getMiniSiteBySlug($slug);
		if (!$this->isPublic()) {
			throw new NotFoundHttpException('This minisite is NOT public and you NOT have the right to see it !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
            'minisite'  => $miniSite,
			'page'		=> $this->getMiniSite()->getHomePage()
        ));
	}
	
	/**
	 * @Route("/{miniSiteSlug}/{pageSlug}", name="minisite_page")
	 */
	public function pageAction($miniSiteSlug, $pageSlug)
	{
		$miniSite = $this->getMiniSiteBySlug($miniSiteSlug);
		if (!$this->isPublic()) {
			throw new NotFoundHttpException('This minisite is NOT public and you NOT have the right to see it !');
		}
		
		$page = $miniSite->findPageBySlug($pageSlug);
		
		if ($page === false) {
			throw new NotFoundHttpException('The page with slug : ' . $pageSlug . ' on minisite with id : ' . $miniSite->getId() . ' is NOT found !');
		}
		
		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
            'minisite'  => $miniSite,
			'page'		=> $page
        ));
	}
	
	/**
	 * @param MiniSiteWidget $widget
	 */
	public function renderWidgetAction($abstractWidget)
	{
		$className	= '\\BNS\\App\\MiniSiteBundle\\Widget\\MiniSiteWidget' . ucfirst(Container::camelize(strtolower($abstractWidget->getMiniSiteWidgetTemplate()->getType())));
		$widget		= $className::create($abstractWidget);
		
		return $this->render($widget->getViewPath(), array(
			'widget'	=> $widget
		));
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\MiniSiteQuery $customQuery
	 */
	protected function getMiniSiteBySlug($slug)
	{
		return parent::getMiniSite(MiniSiteQuery::create()
			->joinWith('MiniSitePage')
			->joinWith('MiniSitePage.MiniSitePageText', \Criteria::LEFT_JOIN)
			->joinWith('MiniSitePageText.User', \Criteria::LEFT_JOIN) // Author
			->joinWith('User.Profile', \Criteria::LEFT_JOIN)
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidget', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidget.MiniSiteWidgetTemplate', \Criteria::LEFT_JOIN)
			->joinWith('MiniSiteWidget.MiniSiteWidgetExtraProperty', \Criteria::LEFT_JOIN)
			->add(MiniSitePagePeer::IS_ACTIVATED, true)
			->add(MiniSitePeer::SLUG, $slug)
			->addAscendingOrderByColumn(MiniSitePagePeer::RANK)
			->addAscendingOrderByColumn(MiniSiteWidgetPeer::RANK)
		);
	}
}