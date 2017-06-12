<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSitePageTextType;
use \BNS\App\MiniSiteBundle\Model\MiniSitePage;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageText;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;
use \BNS\App\MiniSiteBundle\Form\Type\MiniSiteNewsStatusType;

use BNS\App\NotificationBundle\Notification\MinisiteBundle\MinisitePageTextModifiedNotification;
use BNS\App\NotificationBundle\Notification\MinisiteBundle\MinisiteStaticPageModifiedNotification;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use BNS\App\MediaLibraryBundle\Twig\MediaExtension;

/**
 * @Route("/gestion")
 */
class BackController extends AbstractMiniSiteController
{
    /**
	 * @Route("/", name="BNSAppMiniSiteBundle_back")
	 *
	 * @Rights("MINISITE_ACCESS_BACK")
	 */
	public function editoAction()
	{
		return $this->selectPage(null, $this->getMiniSite()->getHomePage());
	}

    /**
     * @Route("/non-editeur", name="BNSAppMiniSiteBundle_back_not_editor")
     * @Rights("MINISITE_ACCESS_BACK")
     */
    public function notEditorAction()
    {
        $page = null;
        foreach($this->getMiniSite()->getMiniSitePages() as $editorPage)
        {
            if($this->isEditorPage($this->getUser(), $editorPage))
            {
                $page = $editorPage;
                break;
            }
        }
        //Si aucune page, on ne sait jamais !
        if($page != null)
        {
            return $this->redirect($this->generateUrl('BNSAppMiniSiteBundle_back'));
        }


        return $this->render('BNSAppMiniSiteBundle:Custom:notEditor.html.twig', array('minisite'   => $this->getMiniSite()));
    }

	/**
	 * @Route("/page/{slug}", name="minisite_manager_page", defaults={"page": null})
	 */
	public function selectPage($slug, $page = null)
	{
		if (null == $page) {
			$page = $this->findPageBySlug($slug);
		}

		// Is editor on Home ?
		if (!$this->isEditorPage($this->getUser(), $page)) {
            $page = null;
            //Sinon on recherche une page sur laquelle il est éditeur
			foreach($this->getMiniSite()->getMiniSitePages() as $editorPage)
            {
                if($this->isEditorPage($this->getUser(), $editorPage))
                {
                    $page = $editorPage;
                    break;
                }
            }
            //Si aucune page, on ne sait jamais !
            if($page == null)
            {
                return $this->redirectNotEditor();
            }
		}

		$methodName = 'renderPage' . ucfirst($page->getType()) . 'Action';

		return $this->$methodName($page);
	}

	/**
	 * @Route("/page/{slug}/editer", name="minisite_manager_page_edit")
	 */
	public function editPageTextAction($slug)
	{
		$page = $this->findPageBySlug($slug);
		if (null == $page->getMiniSitePageText()) {
			$this->createPageText($page);
		}

		// Is editor ?
		if (!$this->isEditorPage($this->getUser(), $page)) {
			return $this->redirectHome();
		}

		$ext = $this->get('twig.extension.resource');
		$page->getMiniSitePageText()->setDraftContent($ext->parsePublicResources($page->getMiniSitePageText()->getDraftContent()));
		$form = $this->createForm(new MiniSitePageTextType($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')), $page->getMiniSitePageText());
		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				/** @var MiniSitePageText $pageText */
				$pageText = $form->getData();
				if ($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')) {
					if ($pageText->getStatus() == 'PUBLISHED') {
						$pageText->setPublishedContent($pageText->getDraftContent());
						$pageText->setPublishedTitle($pageText->getDraftTitle());
						$pageText->setPublishedAt(time());
						$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_PUBLISHED_SUCCESS', array(), 'MINISITE'));
					}
					else {
						$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_UPDATED_SUCCESS', array(), 'MINISITE'));
					}
				}
				else {
					$pageText->setStatus(MiniSitePageTextPeer::STATUS_FINISHED);
					$this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_UPDATED_SUCCESS', array(), 'MINISITE'));
				}

                $pageText->setLastModificationAuthorId($this->getUser()->getId());

				$pageText->save();

                // Attachments save process
                $this->get('bns.media.manager')->saveAttachments($pageText, $this->getRequest());

                $this->get("stat.site")->updateStaticPage();

				// notify page audience that something has changed
				if ($pageText->getStatus() == 'PUBLISHED') {
					$users = $this->getPageAudience($pageText->getMiniSitePage());
					$this->get('notification_manager')->send($users, new MinisitePageTextModifiedNotification($this->get('service_container'), $pageText->getPageId()));
				}

				// Redirect, to avoid refresh
				return $this->redirect($this->generateUrl('minisite_manager_page', array(
					'slug'	=> $page->getSlug()
				)));
			}
		}

		return $this->render('BNSAppMiniSiteBundle:Page:back_page_text_form.html.twig', array(
			'minisite' => $this->getMiniSite(),
			'page'	   => $page,
			'form'	   => $form->createView()
		));
	}

	/**
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSitePage $page
	 *
	 * @return Response
	 */
	public function renderPageTextAction(MiniSitePage $page)
	{
		if (null == $page->getMiniSitePageText()) {
			$this->createPageText($page);
		}

		return $this->render('BNSAppMiniSiteBundle:Page:back_page_text_visualisation.html.twig', array(
			'minisite' => $this->getMiniSite(),
			'page'	   => $page
		));
	}

	/**
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSitePage $page
	 *
	 * @return Response
	 */
	public function renderPageNewsAction(MiniSitePage $page)
	{
		// Récupération des indicateurs pour les filtres
		$indicatorsResultQuery = MiniSitePageNewsQuery::create('mspn')
			->withColumn('count(id)', 'nb_news')
			->select(array('mspn.Status', 'nb_news'))
			->where('mspn.PageId = ?', $page->getId())
			->groupBy('mspn.Status')
		;

		// Si NON admin, on ne récupère que les siens
		if (!$this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION')) {
			$indicatorsResultQuery->where('mspn.AuthorId = ?', $this->getUser()->getId());
		}

		$indicatorsResult = $indicatorsResultQuery->find();
		$indicators = array();
		$statuses = MiniSitePageNewsPeer::getValueSet(MiniSitePageNewsPeer::STATUS);

		foreach ($indicatorsResult as $indicator) {
			$indicators[$statuses[$indicator['mspn.Status']]] = $indicator['nb_news'];
		}

        $session = $this->get('session');
        $session->remove('minisite_page_news_filter');
        $form = $this->createForm(new MiniSiteNewsStatusType(), $session->get('minisite_page_news_filter', array()));

		return $this->render('BNSAppMiniSiteBundle:Page:back_page_news.html.twig', array(
			'minisite'		   => $this->getMiniSite(),
			'page'			   => $page,
			'filterIndicators' => $indicators,
            'filter_form'      => $form->createView()
		));
	}

    /**
     * @Route("/statistiques", name="BNSAppMiniSiteBundle_back_stats")
     * @Route("/statistiques/exporter", name="minisite_manager_stats_export", defaults={"isExport": true})
     *
     * @Rights("MINISITE_ACCESS_BACK")
     */
    public function statsAction($isExport = false)
    {
        $miniSite = $this->getMiniSite();

        $pagesStats = array(
            'name'  => $this->get('translator')->trans('NUMBER_PAGE_VIEW', array(), 'MINISITE'),
            'data'  => array()
        );
        $pagesName = array();

        foreach ($miniSite->getActivatedMiniSitePages() as $page) {
            if($this->get('bns.right_manager')->hasRight('MINISITE_ADMINISTRATION') || $this->isEditorPage($this->getUser(), $page))
            {
                $pagesStats['data'][] = $page->getViews();
                $pagesName[] = $page->getTitle();
            }
        }

        // Export process
        if ($isExport) {
            $response = $this->render('BNSAppMiniSiteBundle:BackStats:export_csv.html.twig', array(
                'title' => array(
                    $this->get('translator')->trans('BACK_PAGE', array(), 'MINISITE'),
                    $this->get('translator')->trans('NUMBER_PAGE_VIEW', array(), 'MINISITE')
                ),
                'pagesName' => $pagesName,
                'data'		=> $pagesStats['data']
            ));

            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="export_graphique_minisite_beneyluschool.csv"');

            return $response;
        }

        return $this->render('BNSAppMiniSiteBundle:BackStats:index.html.twig', array(
            'minisite'   => $this->getMiniSite(),
            'pagesStats' => json_encode(array($pagesStats)),
            'pagesName'  => json_encode($pagesName)
        ));
    }
}
