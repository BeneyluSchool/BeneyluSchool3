<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteNewsStatusType;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSitePageNewsType;
use \BNS\App\MiniSiteBundle\Model\MiniSite;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNews;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSiteQuery;

use BNS\App\NotificationBundle\Notification\MinisiteBundle\MinisitePageNewsPublishedNotification;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\MediaLibraryBundle\Twig\MediaExtension;



/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * @Route("/gestion")
 */
class BackPageNewsController extends AbstractMiniSiteController
{
	/**
	 * @Route("/page/{slug}/actualites", name="minisite_manager_page_news", options={"expose": true})
	 *
	 * @return Response
	 */
	public function getPageNewsAction($slug, MiniSite $miniSite = null, Request $request)
	{
		return $this->renderPageNewsAction($slug, 1, $miniSite, $request);
	}

    /**
     * @Route("/page/{slug}/page/{numberPage}", name="minisite_manager_page_list_page", options={"expose": true})
     *
     * @Rights("MINISITE_ACCESS_BACK")
     */
    public function renderPageNewsAction($slug, $numberPage, $miniSite = null, Request $request)
    {
        if (null == $miniSite) {
            $miniSite = $this->getMiniSite();
        }

        $page = $miniSite->findPageBySlug($slug);
        if (false === $page) {
            throw new NotFoundHttpException('The page with slug : ' . $slug . ' is NOT found !');
        }

        // Is editor ?
        if (!$this->isEditorPage($this->getUser(), $page)) {
            return $this->redirectHome();
        }

        $filterQuery = null;

        $session = $this->get('session');
        $filterForm = $this->createForm(new MiniSiteNewsStatusType(), $session->get('minisite_page_news_filter', array()));

        $data = null;
        $filterForm->handleRequest($request);
        if ($filterForm->isValid()) {
            $data = $filterForm->getData();
            $session->set('minisite_page_news_filter', $data);
        }

        $filterQuery = MiniSitePageNewsQuery::create();

        $data = $session->get('minisite_page_news_filter', array());
        if (isset($data['status']) && is_array($data['status']) && count($data['status']) > 0) {
            $filterQuery->filterByStatus($data['status'], \Criteria::IN);
        }

        // On récupère les actus
        $query = MiniSitePageNewsQuery::create('mspn')
            ->joinWith('mspn.User u') // Author
            ->joinWith('u.Profile p')
            ->joinWith('p.Resource r', \Criteria::LEFT_JOIN)
            ->where('mspn.PageId = ?', $page->getId())
            ->orderBy('mspn.CreatedAt', \Criteria::DESC)
        ;

        // Si NON admin, on ne récupère que les siens
        if (!$this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')) {
            $query->where('mspn.AuthorId = ?', $this->getUser()->getId());
        }

        if (null != $filterQuery) {
            $query->mergeWith($filterQuery);
        }

        $pager = $query->paginate($numberPage, 5);

        // On popule
        $page->replaceMiniSitePageNews($pager->getResults());






        return $this->render('BNSAppMiniSiteBundle:PageNews:back_news_list.html.twig', array(
            'page'		 => $page,
            'pager'		 => $pager,
            'isAjaxCall' => $this->getRequest()->isXmlHttpRequest(),
        ));
    }

	/**
	 * @Route("/actualite/{slug}/editer", name="minisite_manager_page_news_edit", options={"expose"=true})
	 *
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function editPageNewsAction($slug)
	{
		$news = null;
		try {
			$news = $this->findNewsBySlug($slug);
		}
		catch (NotFoundHttpException $e) {
			return $this->redirectHome();
		}

		// Is editor ?
		if (!$this->isEditorPage($this->getUser(), $news->getMiniSitePage())) {
			return $this->redirectHome();
		}

		$ext = $this->get('twig.extension.resource');
		$news->setContent($ext->parsePublicResources($news->getContent()));
		$form = $this->createForm(new MiniSitePageNewsType($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')), $news);
		if ('POST' == $this->getRequest()->getMethod()) {
			$response = $this->savePageNews($form);

			if ($response !== false) {
				return $response;
			}
		}

		return $this->render('BNSAppMiniSiteBundle:PageNews:back_news_form.html.twig', array(
			'minisite'		=> $this->getMiniSite(),
			'news'			=> $news,
			'page'			=> $news->getMiniSitePage(),
			'form'			=> $form->createView(),
			'isEditionMode' => true
		));
	}

	/**
	 * @Route("/actualite/{slug}", name="minisite_manager_page_news_visualisation")
	 *
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function visualizePageNews($slug)
	{
		$news = null;
		try {
			$news = $this->findNewsBySlug($slug);
		}
		catch (NotFoundHttpException $e) {
			return $this->redirectHome();
		}

		// Is editor ?
		if (!$this->isEditorPage($this->getUser(), $news->getMiniSitePage())) {
			return $this->redirectHome();
		}

		return $this->render('BNSAppMiniSiteBundle:PageNews:back_news_visualisation.html.twig', array(
			'news'	   => $news,
			'minisite' => $this->getMiniSite(),
			'page'	   => $news->getMiniSitePage()
		));
	}

	/**
	 * @param \Symfony\Component\Form\Form $form
	 *
	 * @return \Symfony\Component\HttpFoundation\Response Return a redirect response if save success, false otherwise
	 */
	private function savePageNews(&$form)
	{
		$form->bind($this->getRequest());
		if ($form->isValid()) {
			/** @var MiniSitePageNews $news */
			$news = $form->getData();
			if (!$this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')) {
				$news->setStatus(MiniSitePageNewsPeer::STATUS_FINISHED);
			}

			if ($news->getStatus(MiniSitePageNewsPeer::STATUS_PUBLISHED)) {
				$news->setPublishedAt(time());
			}

			$news->save();

            // Attachments save process
            $this->get('bns.media.manager')->saveAttachments($news, $this->getRequest());

			// notify the page audience, on first publication only
			if ($news->isPublished() && !$news->getHasNotified()) {
				$news->setHasNotified(true);
				$news->save();
				$users = $this->getPageAudience($news->getMiniSitePage());
				$this->get('notification_manager')->send($users, new MinisitePageNewsPublishedNotification($this->get('service_container'), $news->getId()));
			}

			// Redirect, to avoid refresh
			return $this->redirect($this->generateUrl('minisite_manager_page_news_visualisation', array(
				'slug'	=> $news->getSlug()
			)));
		}

		return false;
	}


	/**
	 * @param string $view
	 * @param array $parameters
	 * @param \BNS\App\MiniSiteBundle\Controller\Response $response
	public function render($view, array $parameters = array(), Response $response = null)
	{
		// Inject parameter for sidebar custom block
		$parameters['isMiniSiteRoute'] = true;

		return parent::render($view, $parameters, $response);
	}
	 */

	/**
	 * @Route("/page/{slug}/nouvelle-actualite", name="minisite_manager_page_news_new")
	 *
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function createPageNewsAction($slug)
	{
		$miniSite = $this->getMiniSite();
		$page = $miniSite->findPageBySlug($slug);

		// Is editor ?
		if (!$this->isEditorPage($this->getUser(), $page)) {
			return $this->redirectHome();
		}

		$news = new MiniSitePageNews();
		$news->setAuthor($this->getUser());
		$news->setMiniSitePage($page);

		$form = $this->createForm(new MiniSitePageNewsType($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')), $news);
		if ('POST' == $this->getRequest()->getMethod()) {
			$response = $this->savePageNews($form);
			if ($response !== false) {
				return $response;
			}
		}

		return $this->render('BNSAppMiniSiteBundle:PageNews:back_news_form.html.twig', array(
			'minisite'		=> $this->getMiniSite(),
			'news'			=> $news,
			'page'			=> $page,
			'form'			=> $form->createView(),
			'isEditionMode' => false
		));
	}

	/**
	 * @param string $slug
	 *
	 * @return MiniSitePageNews
	 */
	private function findNewsBySlug($slug)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$miniSite = $this->getMiniSite(MiniSiteQuery::create('ms')
			->joinWith('ms.MiniSitePage msp')
			->joinWith('msp.MiniSitePageNews mspn')
			->where('ms.GroupId = ?', $context['id'])
			->where('mspn.Slug = ?', $slug)
			->orderBy('msp.Rank')
		);

		// FIXME add right check

		$pages = $miniSite->getMiniSitePages();
		$news = $pages[0]->getMiniSitePageNewss();

		return $news[0];
	}

	/**
	 * @Route("/actualite/{slug}/supprimer", name="minisite_manager_page_news_delete")
	 *
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function deletePageNewsAction($slug)
	{
		$news = null;
		try {
			$news = $this->findNewsBySlug($slug);
		}
		catch (NotFoundHttpException $e) {
			return $this->redirectHome();
		}

		// Retreive news's page before deletion
		$page = $news->getMiniSitePage();

		// Is editor ?
		if (!$this->isEditorPage($this->getUser(), $page)) {
			return $this->redirectHome();
		}

		// Finally
		$news->delete();

		return $this->redirect($this->generateUrl('minisite_manager_page', array(
			'slug' => $page->getSlug()
		)));
	}
}
