<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSite;
use BNS\App\CoreBundle\Model\MiniSitePage;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\CoreBundle\Model\MiniSitePageNewsQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontPageController extends FrontController
{
	/**
	 * @param type $pageSlug
	 * @param type $miniSiteSlug
	 * @param \BNS\App\CoreBundle\Model\MiniSite $miniSite
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageAction($pageSlug, $miniSiteSlug = null, MiniSite $miniSite = null)
	{
		if (null == $miniSite) {
			$miniSite = $this->getMiniSiteBySlug($miniSiteSlug);
		}
		
		$page = $miniSite->findPageBySlug($pageSlug);
		$methodName = 'renderPage' . ucfirst($page->getType()) . 'Action';
		
		return $this->$methodName($page, $miniSite);
	}
	
	/**
	 * @param MiniSitePage $page
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageTextAction(MiniSitePage $page, MiniSite $miniSite)
	{
		return $this->render('BNSAppMiniSiteBundle:Page:front_page_text.html.twig', array(
			'minisite'	=> $miniSite,
			'page'		=> $page
		));
	}
	
	/**
	 * @param MiniSitePage $page
	 * @param \BNS\App\CoreBundle\Model\MiniSitePageNewsQuery $customQuery
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function renderPageNewsAction(MiniSitePage $page, MiniSite $miniSite, $numberPage = 1, MiniSitePageNewsQuery $customQuery = null)
	{
		$query = MiniSitePageNewsQuery::create()
			->joinWith('User') // Author
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add(MiniSitePageNewsPeer::PAGE_ID, $page->getId())
			->add(MiniSitePageNewsPeer::STATUS, MiniSitePageNewsPeer::STATUS_PUBLISHED_INTEGER)
			->addDescendingOrderByColumn(MiniSitePageNewsPeer::PUBLISHED_AT)
		;
		
		if (null != $customQuery) {
			$query->mergeWith($customQuery);
		}
		
		$pager = $query->paginate($numberPage, 5);
		
		return $this->render('BNSAppMiniSiteBundle:Page:front_page_news.html.twig', array(
			'minisite'	=> $miniSite,
			'page'		=> $page,
			'pager'		=> $pager
		));
	}
}