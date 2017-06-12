<?php

namespace BNS\App\BoardBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\BoardBundle\Model\BoardInformationQuery;
use BNS\App\BoardBundle\Model\BoardInformationPeer;
use BNS\App\BoardBundle\Model\BoardQuery;
use BNS\App\BoardBundle\Model\Board;

/**
 * @Route("/gestion")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackInformationController extends Controller
{
	/**
	 * @Route("/informations", name="board_manager_informations", options={"expose"=true})
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function getInformationsAction($board = null)
	{
		return $this->getInformations(1, $board);
	}

	/**
	 * @Route("/informations/page/{page}", name="board_manager_informations_page")
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function getInformationsPageAction($page, $board = null)
	{
		return $this->getInformations($page, $board);
	}

	/**
	 * @param int $page
	 * @param \BNS\App\BoardBundle\Model\Board $board
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	private function getInformations($page, Board $board = null)
	{
		if ($this->getRequest()->isMethod('POST')) {
			$request = $this->getRequest();
			$sessionName = 'board_informations_filters';

			if ($this->getRequest()->get('filter', false) !== false) {
				$filterName = 'filters';
				$parameter = 'filter';

				// Validate filter
				if ($request->get($parameter) != 'programmed') {
					$valuesSet = BoardInformationPeer::getValueSet(BoardInformationPeer::STATUS);
					if (false === array_search($request->get($parameter), $valuesSet, true)) {
						return $this->getInformationsAction($board);
					}
				}
			}
			else {
				return $this->getInformationsAction($board);
			}

			$filters = $request->getSession()->get($sessionName);
			if (null != $filters && isset($filters[$filterName])) {
				if ($request->get('is_enabled') == 'true') {
					$filters[$filterName][] = $request->get($parameter);
				}
				else {
					foreach ($filters[$filterName] as $key => $filter) {
						if ($filter == $request->get($parameter)) {
							unset($filters[$filterName][$key]);
							break;
						}
					}
				}

				$request->getSession()->set($sessionName, $filters);
			}
			else {
				if (null == $filters) {
					$request->getSession()->set($sessionName, array($filterName => array($request->get($parameter))));
				}
				else {
					$filters[$filterName] = array($request->get($parameter));
					$request->getSession()->set($sessionName, $filters);
				}
			}
		}

		return $this->renderInformations($page, $board);
	}

	/**
	 *
	 * @param int $page
	 * @param \BNS\App\BoardBundle\Model\Board $board
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	private function renderInformations($page, Board $board = null)
	{
		if (null == $board) {
			$board = $this->getBoard();
		}

		BoardInformationQuery::getInformationsFromBoard($board, $pager, $page, $this->getRequest()->getSession()->get('board_informations_filters'));

		return $this->render('BNSAppBoardBundle:Information:back_information_list.html.twig', array(
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
