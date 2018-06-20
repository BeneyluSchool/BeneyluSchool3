<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteCityStatusType;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteNewsStatusType;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSitePageNewsType;
use \BNS\App\MiniSiteBundle\Model\MiniSite;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNews;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNewsQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNews;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSiteQuery;

use BNS\App\NotificationBundle\Notification\MinisiteBundle\MinisitePageNewsModifiedNotification;
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

        $session = $this->get('session');
        if ($page->isCity()) {
            $type = new MiniSiteCityStatusType();
            $filterSessionKey = 'minisite_page_city_filter';
            $filterQuery = MiniSitePageCityNewsQuery::create();
        } else {
            $type = new MiniSiteNewsStatusType();
            $filterSessionKey = 'minisite_page_news_filter';
            $filterQuery = MiniSitePageNewsQuery::create();
        }
        $filterForm = $this->createForm($type, $session->get($filterSessionKey, array()));

        $data = null;
        $filterForm->handleRequest($request);
        if ($filterForm->isValid()) {
            $data = $filterForm->getData();
            $session->set($filterSessionKey, $data);
        }

        $data = $session->get($filterSessionKey, array());
        if (isset($data['status']) && is_array($data['status']) && count($data['status']) > 0) {
            $filterQuery->buildStatusFilter($data['status']);
        }

        $pageId = $page->getId();
        $canManage = true;
        if ($page->isCity() && $miniSite->getGroup()->getType() !== 'CITY') {
            $canManage = false;
        }

        // On récupère les actus
        if ($page->isCity() && $miniSite->getGroup()->getType() !== 'CITY') {
            $query = $this->get('bns.mini_site.city_news_manager')->getCityNewsQueryForPage($page);
        } else {
            $query = MiniSitePageNewsQuery::create('mspn')
                ->where('mspn.PageId = ?', $pageId)
                ->orderBy('mspn.CreatedAt', \Criteria::DESC)
            ;
        }

        $query->joinWith('mspn.User u') // Author
            ->joinWith('u.Profile p')
            ->joinWith('p.Resource r', \Criteria::LEFT_JOIN)
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
            'can_manage' => $canManage,
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

        if ($news->getMiniSitePage()->isCity()) {
            $type = 'BNS\\App\\MiniSiteBundle\\Form\\Type\\MiniSitePageCityNewsType';
            $options = [
                'group' => $news->getMiniSitePage()->getMiniSite()->getGroup()
            ];
        } else {
            $type = 'BNS\\App\\MiniSiteBundle\\Form\\Type\\MiniSitePageNewsType';
            $options = [];
        }

		$ext = $this->get('twig.extension.resource');
		$news->setContent($ext->parsePublicResources($news->getContent()));
		$form = $this->createForm(new $type($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')), $news, $options);
		if ('POST' == $this->getRequest()->getMethod()) {
			$response = $this->savePageNews($form);

			if ($response !== false) {
			    if ($news->isCityNews()) {
                    if ($news->getStatus() == 'PUBLISHED') {
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('CITY_NEWS_FLASH_PUBLISHED_SUCCESS', array(), 'MINISITE'));
                    } else {
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('CITY_NEWS_FLASH_SAVED_SUCCESS', array(), 'MINISITE'));
                    }
                } else {
                    if ($news->getStatus() == 'PUBLISHED') {
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('NEWS_FLASH_PUBLISHED_SUCCESS', array(), 'MINISITE'));
                    } else {
                        $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('NEWS_FLASH_UPDATED_SUCCESS', array(), 'MINISITE'));
                    }
                }
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
		$this->get('bns.media.manager')->bindAttachments($form->getData(), $this->getRequest());
		if ($form->isValid()) {
			/** @var MiniSitePageNews $news */
			$news = $form->getData();
			if (!$this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')) {
				$news->setStatus(MiniSitePageNewsPeer::STATUS_FINISHED);
			}

			if ($news->getStatus() === MiniSitePageNewsPeer::STATUS_PUBLISHED && !$news->isCityNews()) {
				$news->setPublishedAt(time());
			}

			$news->save();

            // Attachments save process
            $this->get('bns.media.manager')->saveAttachments($news, $this->getRequest());

			// notify the page audience
			if ($news->isPublished()) {
				if ($news instanceof MiniSitePageCityNews) {
					if ($news->getHasNotified()) {
						$isFirstPublication = false;
					} else {
						$isFirstPublication = true;
						$news->setHasNotified(true);
						$news->save();
					}
					$schools = $news->getSchools();
					foreach ($schools as $school) {
						$allIds = $this->get('bns.group_manager')->setGroup($school)->getUsersIds();
						$pupilIds = $this->get('bns.group_manager')->getUserIdsByRole('PUPIL', $school);
						$adultIds = array_diff($allIds, $pupilIds);
						$users = UserQuery::create()->findPks($adultIds);
						if ($isFirstPublication) {
							$notification = new MinisitePageNewsPublishedNotification($this->get('service_container'), $news->getId(), $school->getId());
						} else {
							$notification = new MinisitePageNewsModifiedNotification($this->get('service_container'), $news->getId(), $school->getId());
						}
						$this->get('notification_manager')->send($users, $notification);
					}
				} else if (!$news->getHasNotified()) {
					// regular news, first publication only
					$news->setHasNotified(true);
					$news->save();
					$users = $this->getPageAudience($news->getMiniSitePage());
					$this->get('notification_manager')->send($users, new MinisitePageNewsPublishedNotification($this->get('service_container'), $news->getId()));
				}
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

		if ($page->isCity()) {
			// cannot create city news in non-city groups
			if ($page->getMiniSite()->getGroup()->getType() !== 'CITY') {
				return $this->redirectHome();
			}
			$news = new MiniSitePageCityNews();
			$type = 'BNS\\App\\MiniSiteBundle\\Form\\Type\\MiniSitePageCityNewsType';
			$options = [
				'group' => $miniSite->getGroup()
			];
		} else {
			$news = new MiniSitePageNews();
			$type = 'BNS\\App\\MiniSiteBundle\\Form\\Type\\MiniSitePageNewsType';
			$options = [];
		}
		$news->setAuthor($this->getUser());
		$news->setMiniSitePage($page);

		$form = $this->createForm(new $type($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')), $news, $options);
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
        if ($news->isCityNews()) {
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('CITY_NEWS_FLASH_DELETED_SUCCESS', array(), 'MINISITE'));
        } else {
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('NEWS_FLASH_DELETED_SUCCESS', array(), 'MINISITE'));
        }

        // Finally
		$news->delete();

		return $this->redirect($this->generateUrl('minisite_manager_page', array(
			'slug' => $page->getSlug()
		)));
	}

}
