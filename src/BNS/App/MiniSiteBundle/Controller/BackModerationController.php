<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\MiniSiteBundle\Form\Type\MiniSitePageType;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteTypeFiltersType;
use \BNS\App\MiniSiteBundle\Model\MiniSite;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsPeer;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageNewsQuery;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageTextPeer;
use \BNS\App\MiniSiteBundle\Model\MiniSitePageTextQuery;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * @Route("/gestion/moderation")
 */
class BackModerationController extends AbstractMiniSiteController
{
	/**
	 * @Route("/", name="minisite_manager_moderation")
     *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function indexAction()
	{


        $session = $this->get('session');
        $session->remove('minisite_page_news_filter');
        $formType = $this->createForm(new MiniSiteTypeFiltersType(), $session->get('minisite_moderation_types', array()));

		return $this->render('BNSAppMiniSiteBundle:Moderation:index.html.twig', array(
			'minisite'	=> $this->getMiniSite(),
            'filter_form' => $formType->createView()
		));
	}

	/**
	 * @Route("/liste", name="minisite_manager_moderation_list", options={"expose"=true})
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function getPagesAction(Request $request, MiniSite $miniSite = null )
	{
		return $this->renderPagesAction( $request, 1, $miniSite);
	}

	/**
	 * @Route("/page/{numberPage}", name="minisite_manager_moderation_list_page"), options={"expose": true})
	 * @Route("/page/{numberPage}/actualites", name="minisite_manager_moderation_list_page_news", defaults={"type": "NEWS"})
	 * @Route("/page/{numberPage}/pages-statiques", name="minisite_manager_moderation_list_page_text", defaults={"type": "TEXT"})
	 *
	 * @Rights("MINISITE_ADMINISTRATION")
	 */
	public function renderPagesAction(Request $request, $numberPage, $miniSite = null, $type = null )
	{
		// If filters are used
		$session = $this->getRequest()->getSession();

		// Filter types
		$sessionName = 'minisite_moderation_types';
		$types = $this->getRequest()->get('types', null);

		if (null != $types) {
			// Let managing session filters
			$this->manageFilters($sessionName, $types);
		}

		$types = $session->get($sessionName);
		if (count($types) == 1) {
			foreach ($types as $type => $value);
		}

        $filterQuery = null;

        $session = $this->get('session');
        $filterForm = $this->createForm(new MiniSiteTypeFiltersType(), $session->get('minisite_page_news_filter', array()));


        $data = null;
        $filterForm->handleRequest($request);
        if ($filterForm->isValid()) {
            $data = $filterForm->getData();
            $session->set('minisite_page_news_filter', $data);
        }

        $filterQueryNews = MiniSitePageNewsQuery::create();
        $filterQueryText = MiniSitePageTextQuery::create();

        $data = $session->get('minisite_page_news_filter', array());

        $type = $type ? [$type] : [];
        $news = [];
        $texts = [];
        if (isset($data['type'])) {
            if (!is_array($data['type']) && !empty($data['type']) ) {
                $type = [$data['type']];
            } elseif (is_array($data['type'])) {
                $type = $data['type'];
            }
        }

        if (isset($data['status']) && is_array($data['status']) && count($data['status']) > 0) {
            $filterQueryNews->filterByStatus($data['status'], \Criteria::IN);
            $filterQueryText->filterByStatus($data['status'], \Criteria::IN);
        }

		if (null == $miniSite) {
			$miniSite = $this->getMiniSite();
		}



		// On récupère les objets
		if (count($type) === 0 || in_array('NEWS', $type)) {
			$queryNews = MiniSitePageNewsQuery::create('mspn')
				->joinWith('mspn.User u') // Author
				->joinWith('u.Profile p')
				->joinWith('p.Resource r', \Criteria::LEFT_JOIN)
				->joinWith('mspn.MiniSitePage msp')
				->where('msp.MiniSiteId = ?', $miniSite->getId())
				->orderBy('mspn.UpdatedAt', \Criteria::DESC)
			;

			// If filters are used
			$queryNews->mergeWith($filterQueryNews);
            $news = $queryNews->paginate($numberPage, 10);
		}

		if (count($type) === 0 || in_array('TEXT', $type)) {
			$queryText = MiniSitePageTextQuery::create('mspt')
				->joinUserRelatedByAuthorId('a')
				->joinWith('a.Profile pa')
				->joinWith('pa.Resource ra', \Criteria::LEFT_JOIN)
				->joinUserRelatedByLastModificationAuthorId('lma')
				->joinWith('lma.Profile plma')
				->joinWith('plma.Resource rlma', \Criteria::LEFT_JOIN)
				->joinWith('mspt.MiniSitePage msp')
				->where('msp.MiniSiteId = ?', $miniSite->getId())
				->orderBy('mspt.UpdatedAt', \Criteria::DESC)
			;

			// If filters are used
			$queryText->mergeWith($filterQueryText);
            $texts	= $queryText->paginate($numberPage, 10);
		}


		return $this->render('BNSAppMiniSiteBundle:Moderation:moderation_list.html.twig', array(
			'newsArray'	 => $news,
			'textsArray' => $texts
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
