<?php

namespace BNS\App\MiniSiteBundle\Controller;

use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontPageController extends FrontController
{
	/**
	 * @param type $pageSlug
	 * @param type $miniSiteSlug
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSite $miniSite
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageAction($pageSlug, $miniSiteSlug = null, MiniSite $miniSite = null, $isPreview = false)
	{
		if (null == $miniSite) {
			$miniSite = $this->getMiniSiteBySlug($miniSiteSlug);
		}
		
		$page = $miniSite->findPageBySlug($pageSlug);
		$methodName = 'renderPage' . ucfirst($page->getType()) . 'Action';
		
		return $this->$methodName($page, $miniSite, $isPreview);
	}
	
	/**
	 * @param MiniSitePage $page
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageTextAction(MiniSitePage $page, MiniSite $miniSite, $isPreview)
	{
		return $this->render('BNSAppMiniSiteBundle:Page:front_page_text.html.twig', array(
			'minisite'	=> $miniSite,
			'page'		=> $page,
			'isPreview' => $isPreview
		));
	}
	
	/**
	 * @param MiniSitePage $page
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery $customQuery
	 * @Route("/page/{slug}/page/{numberPage}", name="minisite_front_page_list_page")
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
    public function renderPageNewsAction($miniSitePage = null, $miniSite = null, $isPreview = false, $numberPage = 1, $slug = null)
    {
        if($slug != null)
        {
            $miniSitePage = MiniSitePageQuery::create()->findOneBySlug($slug);
        }

        if(!$miniSitePage->getIsPublic())
        {
            if(!$this->get('bns.right_manager')->isAuthenticated() || !$this->get('bns.right_manager')->hasRight('MINISITE_ACCESS'))
            {
                $this->get('bns.right_manager')->forbidIf(true);
            }
        }

        $query = MiniSitePageNewsQuery::create()
            ->filterByMiniSitePage($miniSitePage)
            ->filterByStatus('PUBLISHED')
            ->joinWith('User') // Author
            ->joinWith('User.Profile')
            ->addDescendingOrderByColumn(MiniSitePageNewsPeer::PUBLISHED_AT)
        ;

        $pager = $query->paginate($numberPage, 5);

        return $this->render('BNSAppMiniSiteBundle:Page:front_page_news.html.twig', array(
            'minisite'	=> $miniSitePage->getMiniSite(),
            'page'		=> $miniSitePage,
            'pager'		=> $pager,
            'isAjaxCall' => $this->getRequest()->isXmlHttpRequest()
        ));
    }
}