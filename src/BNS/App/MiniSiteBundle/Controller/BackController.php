<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Form\Type\MiniSitePageTextType;
use BNS\App\CoreBundle\Model\MiniSitePage;
use BNS\App\CoreBundle\Model\MiniSitePageTextPeer;
use BNS\App\CoreBundle\Model\MiniSitePageText;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @Route("/gestion")
 */
class BackController extends AbstractMiniSiteController
{
    /**
     * @Route("/", name="BNSAppMiniSiteBundle_back")
	 * @Rights("MINISITE_ACCESS_BACK")
     */
    public function indexAction()
    {
        return $this->render('BNSAppMiniSiteBundle:Back:index.html.twig', array(
			'minisite'	=> $this->getMiniSite()
		));
    }
	
	/**
	 * @Route("/page/{slug}", name="minisite_manager_page")
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function pageAction($slug)
	{
		// Deleting all filters, make foreach if more than on type filter
		$this->getRequest()->getSession()->remove('minisite_page_news_filter');
		
		return $this->selectPage($slug);
	}
	
	/**
	 * @param string $slug
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function selectPage($slug)
	{
		$page = $this->findPageBySlug($slug);
		$methodName = 'renderPage' . ucfirst($page->getType()) . 'Action';
		
		return $this->$methodName($page);
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\MiniSitePage $page
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageTextAction(MiniSitePage $page)
	{
		if (null == $page->getMiniSitePageText()) {
			// Creating new page text
			$pageText = new MiniSitePageText();
			$pageText->setAuthorId($this->getUser()->getId());
			$pageText->setPageId($page->getId());
			$pageText->save();
			$page->setMiniSitePageText($pageText);
		}
		
		$form = $this->createForm(new MiniSitePageTextType(), $page->getMiniSitePageText());
		
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bindRequest($this->getRequest());

			if ($form->isValid()) {
				$pageText = $form->getData();
				$pageText->setStatus(MiniSitePageTextPeer::STATUS_FINISHED);
				$pageText->save();
				
				// Redirect, to avoid refresh
				return $this->redirect($this->generateUrl('minisite_manager_page', array(
					'slug'	=> $page->getSlug()
				)));
			}
		}
		
		return $this->render('BNSAppMiniSiteBundle:Page:back_page_text_form.html.twig', array(
			'minisite'			=> $this->getMiniSite(),
			'page'				=> $page,
			'form'				=> $form->createView(),
			'isMiniSiteRoute'	=> true
		));
	}
	
	/**
	 * @param \BNS\App\CoreBundle\Model\MiniSitePage $page
	 * 
	 * @return \Symfony\Component\HttpFoundation\Response 
	 */
	public function renderPageNewsAction(MiniSitePage $page)
	{
		return $this->render('BNSAppMiniSiteBundle:Page:back_page_news.html.twig', array(
			'minisite'	=> $this->getMiniSite(),
			'page'		=> $page,
			'pager'		=> $this->pager
		));
	}
}