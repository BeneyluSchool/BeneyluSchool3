<?php

namespace BNS\App\MiniSiteBundle\Controller;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidget;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\Container;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontController extends AbstractMiniSiteController
{
    /**
     * @Route("/", name="BNSAppMiniSiteBundle_front_old")
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_front'));
        /*
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create('ms')
			->joinWith('ms.MiniSitePage msp')
			->joinWith('msp.MiniSitePageText mspt', \Criteria::LEFT_JOIN)
			->joinWith('ms.MiniSiteWidget msw', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetTemplate mswt', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetExtraProperty mswep', \Criteria::LEFT_JOIN)
			->where('msp.IsActivated = ?', true)
			->where('ms.GroupId = ?', $context['id'])
			->orderBy('msp.Rank')
			->orderBy('msw.Rank')
		);

		$homePage = $this->getHomePage($miniSite);
		if (null == $homePage) {
			return $this->redirect($this->generateUrl('home'));
		}

		// Add view
		$this->addViewToPage($homePage);

		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
            'miniSite'  => $miniSite,
			'page'		=> $homePage,
			'isPreview' => false
        ));
        */
    }

	/**
	 * @Route("/{slug}", name="minisite_by_slug")
	 */
	public function bySlugAction($slug)
	{
		$miniSite = $this->getMiniSiteBySlug($slug);
		if (null == $miniSite) {
			return $this->redirect($this->generateUrl('home'));
		}

		$homePage = $this->getHomePage($miniSite);
		if (null == $homePage) {
			return $this->redirect($this->generateUrl('home'));
		}

		// Add view
		$this->addViewToPage($homePage);

        return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_front', ['slug' => $slug]));

//		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
//            'miniSite'  => $miniSite,
//			'page'		=> $homePage,
//			'isPreview' => false
//        ));
	}

	/**
	 * @Route("/{miniSiteSlug}/page/{pageSlug}", name="minisite_page")
	 * @Route("/{miniSiteSlug}/page/{pageSlug}/previsualisation", name="minisite_page_preview", defaults={"isPreview": true})
	 */
	public function pageAction($miniSiteSlug, $pageSlug, $isPreview = false)
	{
		return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_front', ['slug' => $miniSiteSlug]).'/'.$pageSlug);

		$miniSite = $this->getMiniSiteBySlug($miniSiteSlug);
		$page = $miniSite->findPageBySlug($pageSlug);

		if (!$this->canRead($page, $isPreview)) {
			return $this->redirect($this->generateUrl('minisite_by_slug', array(
				'slug'	=> $miniSiteSlug
			)));
		}

		// Add view
		if (!$isPreview) {
			$this->addViewToPage($page);
		}

		// Create page text if not exists
		if ($page->getType() == 'TEXT' && null == $page->getMiniSitePageText()) {
			$this->createPageText($page);
		}

		// Show published content is text page is published
		if ($page->getType() == 'TEXT' && $page->getMiniSitePageText()->isPublished()) {
			$isPreview = false;
		}

		return $this->render('BNSAppMiniSiteBundle:Front:index.html.twig', array(
            'miniSite'  => $miniSite,
			'page'		=> $page,
			'isPreview' => $isPreview
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
	 * @param MiniSiteQuery $customQuery
	 */
	protected function getMiniSiteBySlug($slug)
	{
		return parent::getMiniSite(MiniSiteQuery::create('ms')
			->joinWith('ms.MiniSitePage msp')
			->joinWith('msp.MiniSitePageText mspt', \Criteria::LEFT_JOIN)
			->joinWith('ms.MiniSiteWidget msw', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetTemplate mswt', \Criteria::LEFT_JOIN)
			->joinWith('msw.MiniSiteWidgetExtraProperty mswep', \Criteria::LEFT_JOIN)
			->where('ms.Slug = ?', $slug)
			->orderBy('msp.Rank')
			->orderBy('msw.Rank')
		);
	}


	/**
	 * @param MiniSite $miniSite
	 *
	 * @return MiniSitePage
	 */
	private function getHomePage($miniSite)
	{
		$homePage = $miniSite->getHomePage();
		if (!$this->canRead($homePage)) {
			$homePage = null;

			foreach ($miniSite->getMiniSitePages() as $page) {
				if ($this->canRead($page)) {
					$homePage = $page;
					break;
				}
			}
		}

		return $homePage;
	}

	/**
	 * @param MiniSitePage $page
	 *
	 * @return boolean
	 */
	private function canRead($page, $isPreview = false)
	{
		return !$isPreview && $page->isActivated() && ($page->isPublic() || BNSAccess::isConnectedUser() && $this->get('bns.right_manager')->hasRight('MINISITE_ACCESS')) ||
				$isPreview && ($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION') || $page->isEditor($this->getUser()));
	}
}
