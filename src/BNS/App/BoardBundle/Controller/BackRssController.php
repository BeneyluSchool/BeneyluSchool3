<?php

namespace BNS\App\BoardBundle\Controller;

use BNS\App\CoreBundle\Rss\RssManager;
use BNS\App\BoardBundle\Model\BoardRssQuery;
use BNS\App\BoardBundle\Model\BoardRss;
use BNS\App\BoardBundle\Form\Type\BoardRssType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\BoardBundle\Model\BoardQuery;
use BNS\App\BoardBundle\Model\Board;

/**
 * @Route("/gestion")
 *
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackRssController extends Controller
{
	/**
	 * @Route("/flux-externes/nouveau", name="board_manager_rss_new")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function newAction(Request $request)
	{
		$rss = new BoardRss();
		$rss->setEnable(true);
		$board = $this->getBoard();

		$form = $this->createForm(new BoardRssType(), $rss);

		if ($request->isMethod('post')) {
			$form->bind($request);
			if ($form->isValid()) {
				$rss->setBoard($board);
				$rss->setUser($this->getUser());
				$rss->save();

				return $this->redirect($this->generateUrl('board_manager_rss'));
			}
		}

		return $this->render('BNSAppBoardBundle:Back:rss_form.html.twig', array(
			'board' => $board,
			'form' => $form->createView(),
			'isEditionMode' => false
		));
	}

	/**
	 * @Route("/flux-externes/{id}/edition", name="board_manager_rss_edit")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function editAction(Request $request, $id)
	{
		$rss = BoardRssQuery::create()->filterByBoard($this->getBoard())->findPk($id);
		if (!$rss) {
			throw new NotFoundHttpException('invalid rss : ' . $id);
		}
		
		$board = $this->getBoard();
		$form = $this->createForm(new BoardRssType(), $rss);

		if ($request->isMethod('post')) {
			$form->bind($request);
			if ($form->isValid()) {
				$rss->save();

				return $this->redirect($this->generateUrl('board_manager_rss_view', array(
					'id' => $rss->getId()
				)));
			}
		}

		return $this->render('BNSAppBoardBundle:Back:rss_form.html.twig', array(
			'board' => $board,
			'form' => $form->createView(),
			'rss' => $rss,
			'isEditionMode' => true
		));
	}

	/**
	 * @Route("/flux-externes/{id}/suppression", name="board_manager_rss_delete")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function deleteAction(Request $request, $id)
	{
		$rss = BoardRssQuery::create()->filterByBoard($this->getBoard())->findPk($id);

		if (!$rss) {
			throw new NotFoundHttpException('The rss with id : ' . $id . ' is NOT found !');
		}

		// Process
		$rss->delete();

		if (!$this->getRequest()->isXmlHttpRequest()) {
			$this->get('session')->getFlashBag()->add('success', "Le flux externe a été supprimé avec succès.");

			return $this->redirect($this->generateUrl('board_manager_rss'));
		}

		return new Response();
	}

	/**
	 * @Route("/flux-externes/{id}/visualisation", name="board_manager_rss_view")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function viewAction(Request $request, $id)
	{
		$rss = BoardRssQuery::create()->filterByBoard($this->getBoard())->findPk($id);
		if (!$rss) {
			throw new NotFoundHttpException('Invalid rss flux: ' . $id);
		}
		
		$rssManager = new RssManager($this->get('snc_redis.default'));

		return $this->render('BNSAppBoardBundle:Rss:back_rss_visualisation.html.twig', array(
			'rss' => $rss,
			'items' => $rssManager->getRss($rss->getUrl())
		));

	}


	/**
	 * @Route("/flux-externes/page/{page}", name="board_manager_rss_list", options={"expose"=true}, defaults={"page"=1})
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function getRssListAction($page = 1, Board $board = null)
	{
		return $this->getRssList($page, $board);
	}

	/**
	 * @param int $page
	 * @param \BNS\App\BoardBundle\Model\Board $board
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	private function getRssList($page, Board $board = null)
	{
		if ($this->getRequest()->isMethod('POST')) {
			$request = $this->getRequest();
			$sessionName = 'board_rss_filters';
			$filterEnable = null;
			
			if ($request->request->get('enabled', false) !== $request->request->get('disabled', false)) {
				$filterEnable = 'true' == $request->request->get('enabled', false);
			}
			
			$request->getSession()->set($sessionName, $filterEnable);
		}

		return $this->renderRssList($page, $board);
	}

	/**
	 *
	 * @param int $page
	 * @param \BNS\App\BoardBundle\Model\Board $board
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	private function renderRssList($page, Board $board = null)
	{
		if (null == $board) {
			$board = $this->getBoard();
		}

		$filterEnable =  $this->getRequest()->getSession()->get('board_rss_filters', null);

		$pager = BoardRssQuery::create()
			->_if(null !== $filterEnable)
				->filterByEnable($filterEnable)
			->_endif()
			->filterByBoard($board)
			->orderByCreatedAt(\Criteria::DESC)
			->paginate($page, 10)
		;

		return $this->render('BNSAppBoardBundle:Rss:back_rss_list.html.twig', array(
			'board' => $board,
			'pager' => $pager,
			'isAjaxCall' => $this->getRequest()->isXmlHttpRequest()
		));
	}

	/**
	 * @return Board
	 *
	 * @throws NotFoundHttpException
	 */
	private function getBoard()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$board = BoardQuery::create()->filterByGroupId($context['id'])->findOne();

		if (!$board) {
			throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
		}

		return $board;
	}
}