<?php

namespace BNS\App\BoardBundle\Controller;

use BNS\App\BoardBundle\Model\BoardRssQuery;
use BNS\App\BoardBundle\Form\Model\BoardInformationFormModel;
use BNS\App\BoardBundle\Form\Type\BoardInformationType;
use BNS\App\BoardBundle\Model\BoardInformationPeer;
use BNS\App\BoardBundle\Model\BoardInformationQuery;
use BNS\App\BoardBundle\Model\BoardPeer;
use BNS\App\BoardBundle\Model\BoardQuery;
use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @Route("/gestion")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BackController extends Controller
{
	/**
	 * @Route("/", name="BNSAppBoardBundle_back")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function indexAction(Request $request)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$board = BoardQuery::create()->filterByGroupId($context['id'])->findOneOrCreate();

		if (!$board) {
			throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
		}

		if ($board->isNew()) {
			$board->save();
		}

		// Gestion des filtres d'informations
		$request->getSession()->remove('board_informations_filters');

		// Récupération des indicateurs pour les filtres
		$indicatorsResultQuery = BoardInformationQuery::create('a')
			->withColumn('count(id)', 'nb_information')
			->select(array('a.Status', 'nb_information'))
			->where('a.BoardId = ?', $board->getId())->groupBy('a.Status')
		;

		$indicatorsResult = $indicatorsResultQuery->find();
		$indicators = array();
		$statuses = BoardInformationPeer::getValueSet(BoardInformationPeer::STATUS);
		
		foreach ($indicatorsResult as $indicator) {
			$indicators[$statuses[$indicator['a.Status']]] = $indicator['nb_information'];
		}

		// Les statuts PUBLISHED peuvent aussi être PROGRAMMED, on affine et on recalcule
		$programmedIndicatorQuery = BoardInformationQuery::create('a')
			->where('a.BoardId = ?', $board->getId())
			->where('a.Status = ?', 'PUBLISHED')
			->where('a.PublishedAt > ?', time())
		;

		$programmedIndicator = $programmedIndicatorQuery->count();

		if (0 < $programmedIndicator) {
			$indicators['PUBLISHED'] -= $programmedIndicator;
			$indicators['PROGRAMMED'] = $programmedIndicator;
		}

		return $this->render('BNSAppBoardBundle:Back:index_manager.html.twig', array(
			'board' => $board,
			'filterIndicators' => $indicators
		));
	}

	/**
	 * @Route("/nouvelle-information", name="board_manager_new_information")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function newInformationAction()
	{
		$context = $this->get('bns.right_manager')->getContext();
		$board = BoardQuery::create()
			->filterByGroupId($context['id'])
		->findOne();

		if (!$board) {
			throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
		}

		$form = $this->createForm(new BoardInformationType($this->container), new BoardInformationFormModel());

		return $this->render('BNSAppBoardBundle:Back:information_form.html.twig', array(
			'board' => $board,
			'form' => $form->createView(),
			'information' => $form->getData()->getInformation(),
			'isEditionMode' => false
		));
	}

	/**
	 * @Route("/nouvelle-information/terminer", name="board_manager_new_information_finish")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function finishNewInformationAction()
	{
		if ('POST' == $this->getRequest()->getMethod()) {
			$context = $this->get('bns.right_manager')->getContext();
			$model = new BoardInformationFormModel();
			$form = $this->createForm(new BoardInformationType($this->container), $model, array('validation_groups' => array('Default', 'new')));
			$form->bind($this->getRequest());
			$this->get('bns.media.manager')->bindAttachments($model->getInformation(), $this->getRequest());
			$board = BoardQuery::create()
				->filterByGroupId($context['id'])
			->findOne();

			if (!$board) {
				throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
			}

			if ($form->isValid()) {
				$model = $form->getData();

				// Finally
				$model->save($this->get('bns.right_manager'), $this->getUser(), $this->getRequest(), $board);
                
                //statistic action
                $this->get("stat.board")->publishMessage();


				if (null !== $model->getInformation()->getId()) {
					return $this->redirect($this->generateUrl('board_manager_information_visualisation', array(
						'informationSlug' => $model->getInformation()->getSlug()
					)));
				}
				else {
					return $this->redirect($this->generateUrl('BNSAppBoardBundle_back'));
				}
			}
            
			return $this->render('BNSAppBoardBundle:Back:information_form.html.twig', array(
				'board' => $board,
				'form' => $form->createView(),
				'information' => $form->getData()->getInformation(),
				'isEditionMode' => false
			));
		}

		return $this->redirect($this->generateUrl('BNSAppBoardBundle_back'));
	}

	/**
	 * @Route("/information/{informationSlug}/editer", name="board_manager_edit_information", options={"expose"=true})
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function editInformationAction($informationSlug)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$informations = BoardInformationQuery::create()
			->joinWith('Board')
			->joinWith('User')
			->add(BoardPeer::GROUP_ID, $context['id'])
			->add(BoardInformationPeer::SLUG, $informationSlug)
		->find();

		if (!isset($informations[0])) {
			throw new NotFoundHttpException('Information not found for slug : ' . $informationSlug . ' !');
		}

		$information = $informations[0];
		$isEditionMode = true;
		$form = $this->createForm(new BoardInformationType($this->container, false), new BoardInformationFormModel(clone $information));

		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bind($this->getRequest());
			$this->get('bns.media.manager')->bindAttachments($information, $this->getRequest());

			if ($form->isValid()) {
				$model = $form->getData();

				// Finally
				$model->save($this->get('bns.right_manager'), $this->getUser(), $this->getRequest());

				if ($this->getUser()->getId() == $model->getInformation()->getAuthorId()) {
					$message = 'Votre information a bien été modifiée.';
				}
				else {
					$message = "L'information a bien été modifiée avec succès.";
				}
				
				$this->get('session')->getFlashBag()->add('success', $message);

				return $this->redirect($this->generateUrl('board_manager_information_visualisation', array(
					'informationSlug' => $model->getInformation()->getSlug()
				)));
			}
		}

		$board = BoardQuery::create()
			->filterByGroupId($context['id'])
		->findOne();

		if (!isset($board)) {
			throw new NotFoundHttpException('Board not found for group id : ' . $context['id'] . ' !');
		}

		return $this->render('BNSAppBoardBundle:Back:information_form.html.twig', array(
			'board' => $board,
			'form' => $form->createView(),
			'information' => $information,
			'isEditionMode' => $isEditionMode
		));
	}

	/**
	 * @Route("/flux-externes", name="board_manager_rss")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function rssIndexAction(Request $request)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$board = BoardQuery::create()->filterByGroupId($context['id'])->findOne();

		if (null == $board) {
			throw new NotFoundHttpException('The board with the group id : ' . $context['id'] . ' is NOT found ! ');
		}

		// Gestion des filtres d'informations
		$request->getSession()->remove('board_rss_filters');

		$rssEnable = BoardRssQuery::create()
			->filterByBoard($board)
			->filterByEnable(true)
		->count();

		$rssDisable = BoardRssQuery::create()
			->filterByBoard($board)
			->filterByEnable(false)
		->count();

		return $this->render('BNSAppBoardBundle:Back:rss_manager.html.twig', array(
			'board' => $board,
			'rssEnable' => $rssEnable,
			'rssDisable' => $rssDisable
		));
	}

	/**
	 * @Route("/visualisation/{informationSlug}", name="board_manager_information_visualisation")
	 *
	 * @param string $informationSlug
	 */
	public function visualisationAction($informationSlug)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$informations = BoardInformationQuery::create()
			->joinWith('Board')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add(BoardPeer::GROUP_ID, $context['id'])->add(BoardInformationPeer::SLUG, $informationSlug)
		->find();

		if (0 == count($informations)) {
			throw new NotFoundHttpException('Information not found for slug : ' . $informationSlug . ' !');
		}

		return $this->render('BNSAppBoardBundle:Information:back_information_visualisation.html.twig', array(
			'information' => $informations[0]
		));
	}
}
