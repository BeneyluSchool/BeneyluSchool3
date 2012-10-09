<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSite;
use BNS\App\CoreBundle\Model\MiniSitePageNews;
use BNS\App\CoreBundle\Model\MiniSitePageNewsQuery;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Form\Type\MiniSitePageNewsType;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 * @Route("/gestion")
 */
class BackPageNewsController extends AbstractMiniSiteController
{
	/**
	 * @Route("/page/{slug}/news", name="minisite_manager_page_list")
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function getPageNewsAction($slug, MiniSite $miniSite = null)
	{
		return $this->renderPageNewsAction($slug, 1, $miniSite);
	}
	
	/**
	 * @Route("/page/{slug}/news/page/{numberPage}", name="minisite_manager_page_list_page")
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function renderPageNewsAction($slug, $numberPage, $miniSite = null)
	{
		$filterQuery = null;
		
		// If filters are used
		if ('POST' == $this->getRequest()->getMethod()) {
			$filter = $this->getRequest()->get('filter', null);
			if (null == $filter) {
				throw new \InvalidArgumentException('There is missing parameter "filter" !');
			}

			$session = $this->getRequest()->getSession();
			$sessionName = 'minisite_page_news_filter';

			// Let managing session filters
			$this->manageFilters($sessionName, $filter);

			$filters = $session->get($sessionName);
			$filterQuery = MiniSitePageNewsQuery::create();

			foreach ($filters as $statusId => $value) {
				$filterQuery->addOr(MiniSitePageNewsPeer::STATUS, $statusId);
			}
		}
		
		if (null == $miniSite) {
			$miniSite = $this->getMiniSite();
		}
		
		$page = $miniSite->findPageBySlug($slug);
		if (false === $page) {
			throw new NotFoundHttpException('The page with slug : ' . $slug . ' is NOT found !');
		}
		
		// On récupère les actus
		$query = MiniSitePageNewsQuery::create()
			->joinWith('User') // Author
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->filterByPageId($page->getId())
			->addDescendingOrderByColumn(MiniSitePageNewsPeer::CREATED_AT)
			->limit(5)
		;

		if (null != $filterQuery) {
			$query->mergeWith($filterQuery);
		}

		$this->pager = $query->paginate($numberPage);

		// On popule
		$page->replaceMiniSitePageNews($this->pager->getResults());
		
		return $this->render('BNSAppMiniSiteBundle:PageNews:back_news_list.html.twig', array(
			'page'	=> $page
		));
	}
	
	/**
	 * @Route("/page/news/{slug}", name="minisite_manager_page_news_edit", options={"expose"=true})
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function editPageNewsAction($slug)
	{
		$news = MiniSitePageNewsQuery::create()
			->add(MiniSitePageNewsPeer::SLUG, $slug)
		->findOne();
		
		if (null == $news) {
			throw new NotFoundHttpException('The page news with slug : ' . $slug . ' is NOT found !');
		}
		
		$form = $this->createForm(new MiniSitePageNewsType(), $news);
		if ('POST' == $this->getRequest()->getMethod()) {
			$response = $this->savePageNews($form);
			if ($response !== false) {
				return $response;
			}
		}
		
		return $this->render('BNSAppMiniSiteBundle:Page:back_page_news_form.html.twig', array(
			'minisite'	=> $this->getMiniSite(),
			'news'		=> $news,
			'form'		=> $form->createView()
		));
	}
	
	/**
	 * @param \Symfony\Component\Form\Form $form
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response Return a redirect response if save success, false otherwise
	 */
	private function savePageNews(&$form)
	{
		$form->bindRequest($this->getRequest());
		if ($form->isValid()) {
			$news = $form->getData();
			$news->setStatus(MiniSitePageNewsPeer::STATUS_FINISHED);
			$news->save();

			// Redirect, to avoid refresh
			return $this->redirect($this->generateUrl('minisite_manager_page', array(
				'slug'	=> $news->getMiniSitePage()->getSlug()
			)));
		}
		
		return false;
	}
	
	/**
	 * @param string $view
	 * @param array $parameters
	 * @param \BNS\App\MiniSiteBundle\Controller\Response $response
	 */
	public function render($view, array $parameters = array(), Response $response = null)
	{
		// Inject parameter for sidebar custom block
		$parameters['isMiniSiteRoute'] = true;
		
		return parent::render($view, $parameters, $response);
	}
	
	/**
	 * @Route("/page/{slug}/news/nouveau", name="minisite_manager_page_news_new")
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function newPageNewsAction($slug)
	{
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageBySlug($slug);
		
		if (false === $page) {
			throw new NotFoundHttpException('The page with slug : ' . $slug . ' is NOT found !');
		}
		
		$news = new MiniSitePageNews();
		$news->setMiniSitePage($page);
		
		$form = $this->createForm(new MiniSitePageNewsType(), $news);
		if ('POST' == $this->getRequest()->getMethod()) {
			$response = $this->savePageNews($form);
			if ($response !== false) {
				return $response;
			}
		}
		
		return $this->render('BNSAppMiniSiteBundle:Page:back_page_news_form.html.twig', array(
			'minisite'	=> $this->getMiniSite(),
			'news'		=> $news,
			'form'		=> $form->createView(),
			'isNew'		=> true
		));
	}
}