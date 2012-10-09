<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSite;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSitePageNewsQuery;
use BNS\App\CoreBundle\Model\MiniSitePageTextQuery;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\CoreBundle\Model\MiniSitePageTextPeer;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion/moderation")
 */
class BackModerationController extends AbstractMiniSiteController
{
	/**
	 * @Route("/", name="minisite_manager_moderation")
	 */
	public function indexAction()
	{
		$this->getRequest()->getSession()->remove('minisite_moderation_filter');
		
		return $this->render('BNSAppMiniSiteBundle:Moderation:index.html.twig', array(
			'minisite'	=> $this->getMiniSite()
		));
	}
	
	/**
	 * @Route("/pages", name="minisite_manager_moderation_page_list")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function getPagesAction(MiniSite $miniSite = null)
	{
		return $this->renderPagesAction(1, $miniSite);
	}
	
	/**
	 * @Route("/pages/page/{numberPage}", name="minisite_manager_moderation_page_list_with_pager")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function renderPagesAction($numberPage, $miniSite = null)
	{
		// If filters are used
		if ('POST' == $this->getRequest()->getMethod()) {
			$filter = $this->getRequest()->get('filter', null);
			if (null == $filter) {
				throw new \InvalidArgumentException('There is missing parameter "filter" !');
			}

			$session = $this->getRequest()->getSession();
			$sessionName = 'minisite_moderation_filter';

			// Let managing session filters
			$this->manageFilters($sessionName, $filter);

			$filters = $session->get($sessionName);
			$filterQueryNews = MiniSitePageNewsQuery::create();
			$filterQueryText = MiniSitePageTextQuery::create();

			foreach ($filters as $statusId => $value) {
				$filterQueryNews->addOr(MiniSitePageNewsPeer::STATUS, $statusId);
				$filterQueryText->addOr(MiniSitePageTextPeer::STATUS, $statusId);
			}
		}
		
		if (null == $miniSite) {
			$miniSite = $this->getMiniSite();
		}
		
		// On récupère les objets
		$queryNews = MiniSitePageNewsQuery::create()
			->joinWith('User') // Author
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->joinWith('MiniSitePage')
			->add(MiniSitePageNewsPeer::STATUS, MiniSitePageNewsPeer::STATUS_PUBLISHED_INTEGER, \Criteria::ALT_NOT_EQUAL)
			->add(MiniSitePagePeer::MINI_SITE_ID, $miniSite->getId())
			->addDescendingOrderByColumn(MiniSitePageNewsPeer::CREATED_AT)
		;
		$queryText = MiniSitePageTextQuery::create()
			->joinWith('User') // Author
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->joinWith('MiniSitePage')
			->add(MiniSitePagePeer::MINI_SITE_ID, $miniSite->getId())
			->add(MiniSitePageTextPeer::STATUS, MiniSitePageTextPeer::STATUS_PUBLISHED_INTEGER, \Criteria::ALT_NOT_EQUAL)
			->addDescendingOrderByColumn(MiniSitePageTextPeer::CREATED_AT)
		;

		if (isset($filters) && null != $filters) {
			$queryNews->mergeWith($filterQueryNews);
			$queryText->mergeWith($filterQueryText);
		}
		
		$news	= $queryNews->find();
		$texts	= $queryText->find();

		return $this->render('BNSAppMiniSiteBundle:Moderation:moderation_list.html.twig', array(
			'newsArray'		=> $news,
			'textsArray'	=> $texts
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
		$parameters['isModerationRoute'] = true;
		
		return parent::render($view, $parameters, $response);
	}
}