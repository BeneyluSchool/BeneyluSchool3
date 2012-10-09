<?php

namespace BNS\App\MiniSiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\MiniSitePeer;
use BNS\App\CoreBundle\Model\MiniSitePageNews;
use BNS\App\CoreBundle\Model\MiniSitePageNewsQuery;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\CoreBundle\Model\MiniSitePageTextPeer;
use BNS\App\CoreBundle\Model\MiniSitePagePeer;
use BNS\App\CoreBundle\Model\MiniSitePageText;

/**
 * @Route("/gestion")
 */
class BackActionBarController extends AbstractMiniSiteController
{
    /**
	 * @Route("/page/news/{slug}/status/{statusId}", name="ministe_manager_page_news_status")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function editPageNewsStatusAction($slug, $statusId)
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$news = MiniSitePageNewsQuery::create()
				->joinWith('MiniSitePage')
				->join('MiniSitePage.MiniSite')
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->add(MiniSitePageNewsPeer::SLUG, $slug)
			->findOne();
			
			if (null == $news) {
				throw new NotFoundHttpException('The news with slug : ' . $slug . ' is NOT found !');
			}
			
			// Status exists ?
			$statuses = MiniSitePageNewsPeer::getValueSet(MiniSitePageNewsPeer::STATUS);
			if (!isset($statuses[$statusId])) {
				throw new InvalidArgumentException('The status id : ' . $statusId . ' does NOT exist !');
			}
			
			// Is logic ?
			if (!in_array($statusId, MiniSitePageNews::$ALLOWED_NEW_STATUSES[$news->getStatus()])) {
				throw new AccessDeniedHttpException('You are NOT allowed to edit the news status !');
			}
			
			// Setting
			$news->setStatus($statuses[$statusId]);
			if ($statuses[$statusId] == MiniSitePageNewsPeer::STATUS_PUBLISHED) {
				$news->setPublishedAt(time());
			}
			
			// Finally
			$news->save();

			$response = array(
				'button' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_news_inner_status_button.html.twig', array('news' => $news)),
				'dropdown' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_news_inner_status_dropdown.html.twig', array('news' => $news))
			);
			
			return new Response(json_encode($response));
		}
		
		return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back'));
	}
	
	/**
	 * @Route("/page/{slug}/status/{statusId}", name="ministe_manager_page_status")
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function editPageStatusAction($slug, $statusId)
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$page = MiniSitePageTextQuery::create()
				->joinWith('MiniSitePage')
				->joinWith('MiniSitePage.MiniSite')
				->add(MiniSitePeer::GROUP_ID, $context['id'])
				->add(MiniSitePagePeer::SLUG, $slug)
			->findOne();
			
			if (null == $page) {
				throw new NotFoundHttpException('The page with slug : ' . $slug . ' is NOT found !');
			}
			
			// Status exists ?
			$statuses = MiniSitePageTextPeer::getValueSet(MiniSitePageTextPeer::STATUS);
			if (!isset($statuses[$statusId])) {
				throw new InvalidArgumentException('The status id : ' . $statusId . ' does NOT exist !');
			}
			
			// Is logic ?
			if (!in_array($statusId, MiniSitePageText::$ALLOWED_NEW_STATUSES[$news->getStatus()])) {
				throw new AccessDeniedHttpException('You are NOT allowed to edit the news status !');
			}
			
			// Setting
			$page->setStatus($statuses[$statusId]);
			if ($statuses[$statusId] == MiniSitePageNewsPeer::STATUS_PUBLISHED) {
				$page->setPublishedContent($page->getDraftContent());
				$page->setPublishedTitle($page->getDraftTitle());
				$page->setPublishedAt(time());
			}
			
			// Finally
			$page->save();

			$response = array(
				'button' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_text_inner_status_button.html.twig', array('text' => $page)),
				'dropdown' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_text_inner_status_dropdown.html.twig', array('text' => $page))
			);
			
			return new Response(json_encode($response));
		}
		
		return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back'));
	}
}