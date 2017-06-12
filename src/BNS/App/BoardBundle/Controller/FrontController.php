<?php

namespace BNS\App\BoardBundle\Controller;

use \BNS\App\BoardBundle\Model\Board;
use \BNS\App\BoardBundle\Model\BoardInformationQuery;
use \BNS\App\BoardBundle\Model\BoardQuery;
use \BNS\App\BoardBundle\Model\BoardRssQuery;
use \BNS\App\CoreBundle\Rss\RssManager;
use \BNS\App\CoreBundle\Annotation\Rights;
use \Criteria;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author <sylvain.lorinet@pixel-cookers.com>
 */
class FrontController extends Controller
{
	private $board;

	/**
	 * @Route("/", name="BNSAppBoardBundle_front")
	 * @Rights("BOARD_ACCESS")
	 */
	public function indexAction()
	{
		return $this->renderBoard(1, $this->getBoard());
	}

	/**
	 * @Route("/page/{page}", name="board_informations_page")
	 *
	 * @Rights("BOARD_ACCESS")
	 */
	public function getInformationsPageAction($page, $board = null)
	{
		if (null == $board) {
			$board = $this->getBoard();
		}

		return $this->renderBoard($page, $board);
	}

	/**
	 * @param int $page
	 * @param Board $board
	 * @param array $queries
	 *
	 * @return Response
	 */
	private function renderBoard($page, Board $board, array $queries = array(), array $parameters = array())
	{
		if (!isset($queries['BoardInformationQuery'])) {
			$queries['BoardInformationQuery'] = BoardInformationQuery::create('bi')
				->filterByBoard($board)
				->joinWith('User')
				->joinWith('User.Profile')
				->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
				->filterByIsPublished()
				->orderByIsAlert(Criteria::DESC)
				->orderByPublishedAt(Criteria::DESC)
			;
		}

		$parameters['board'] = $board;
		$parameters['pager'] = $queries['BoardInformationQuery']->paginate($page, 5);
		$parameters['rssData'] = $this->getRssData();

		return $this->render('BNSAppBoardBundle:Front:index.html.twig', $parameters);
	}

	private function getRssData()
	{
		$rssList = BoardRssQuery::create()
			->orderByCreatedAt(\Criteria::DESC)
			->filterByEnable(true)
			->filterByBoard($this->getBoard())
		->find();

		$rssData = array();
		$rssManager = new RssManager($this->get('snc_redis.default'));

		foreach ($rssList as $rss) {
			$items = array_slice($rssManager->getRss($rss->getUrl()), 0, 3);
			if (count($items) > 0) {
				$rssData[] = array(
					'rss_title' => $rss->getTitle(),
					'rss_items' => $items
				);
			}
		}

		return $rssData;
	}

	/**
	 * @return Board
	 *
	 * @throws NotFoundHttpException
	 */
	private function getBoard()
	{
		if (null === $this->board) {
			$context = $this->get('bns.right_manager')->getContext();
			$board = BoardQuery::create()->filterByGroupId($context['id'])->findOneOrCreate();

			if (!isset($board)) {
				throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
			}

			if ($board->isNew()) {
				$board->save();
			}

			$this->board = $board;
		}

		return $this->board;
	}
}