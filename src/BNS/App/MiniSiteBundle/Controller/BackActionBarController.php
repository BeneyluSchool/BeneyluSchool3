<?php

namespace BNS\App\MiniSiteBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePagePeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageText;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;
use BNS\App\MiniSiteBundle\Model\MiniSitePageTextQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePeer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @Route("/gestion")
 */
class BackActionBarController extends AbstractMiniSiteController
{
    /**
	 * @Route("/actualite/{slug}/statut/{statusId}", name="ministe_manager_page_news_status")
	 *
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

			// If moderated, hide item
			$mustHide = $this->mustHide($statusId);

			$response = array(
				'menu' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_news_action_bar.html.twig', array('news' => $news)),
				'status'    => strtolower($news->getStatus()),
				'must_hide' => $mustHide
			);

			return new Response(json_encode($response));
		}

		return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back'));
	}

	/**
	 * @Route("/page/{slug}/statut/{statusId}", name="ministe_manager_page_status")
	 *
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
			if (!in_array($statusId, MiniSitePageText::$ALLOWED_NEW_STATUSES[$page->getStatus()])) {
				throw new AccessDeniedHttpException('You are NOT allowed to edit the new status !');
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

			// If moderated, hide item
			$mustHide = $this->mustHide($statusId);

			$response = array(
				'menu' => $this->renderView('BNSAppMiniSiteBundle:ActionBar:back_text_action_bar.html.twig', array('text' => $page)),
				'status'    => strtolower($page->getStatus()),
				'must_hide' => $mustHide
			);

			return new Response(json_encode($response));
		}

		return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back'));
	}

	/**
	 * @return boolean
	 */
	private function mustHide($statusId)
	{
		$filters = $this->getRequest()->getSession()->get('minisite_moderation_filter');
        $filters = $this->getRequest()->getSession()->get('minisite_page_news_filter', array());
		if (isset($filters['status']) && count($filters['status']) && !isset($filters['status'][$statusId])) {
			return true;
		}

		return false;
	}
}
