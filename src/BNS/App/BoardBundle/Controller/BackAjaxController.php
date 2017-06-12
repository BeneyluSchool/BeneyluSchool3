<?php

namespace BNS\App\BoardBundle\Controller;

use BNS\App\BoardBundle\Model\BoardRss;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\BoardBundle\Model\BoardInformation;
use BNS\App\BoardBundle\Model\BoardQuery;
use BNS\App\BoardBundle\Model\BoardPeer;
use BNS\App\BoardBundle\Model\BoardInformationQuery;
use BNS\App\BoardBundle\Model\BoardInformationPeer;
use BNS\App\BoardBundle\Form\Type\BoardInformationType;

/**
 * @Route("/gestion")
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackAjaxController extends Controller
{
	/**
	 * @Route("/brouillon/sauvegarder", name="board_manager_draft_save")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function saveDraftAction()
	{
		if ('POST' == $this->getRequest()->getMethod() && $this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$params = $this->getRequest()->get('board_information_form');

			if (isset($params['id']) && null != $params['id']) {
				$information = BoardInformationQuery::create()
					->join('Board')
					->add(BoardPeer::GROUP_ID, $context['id'])
				->findPk($params['id']);

				if (null == $information) {
					$information = new BoardInformation();
				}
			}
			else {
				$information = new BoardInformation();
			}

			$form = $this->createForm(new BoardInformationType($this->container), $information);
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				$information = $form->getData();
				$information->setUpdatedAt(time());
				$information->setStatus(BoardInformationPeer::STATUS_DRAFT);
				$information->setIsStar(false); // always false when is draft

				// Is new ?
				if (null == $information->getCreatedAt()) {
					$information->setCreatedAt(time());
					$information->setBoardId($context['id']);
					$information->setAuthorId($this->getUser()->getId());
				}

				// Finally
				$information->save();

				return new Response(json_encode(array(
					'response'	=> true,
					'informationId'	=> $information->getId()
				)));
			}
			else {
				$errorsArray = array();
				foreach ($form->getChildren() as $children) {
					if (count($children->getErrors()) > 0) {
						foreach ($children->getErrors() as $error) {
							$errorsArray[] = $error->getMessage();
						}
					}
				}

				if (count($errorsArray) > 1) {
					$errors = '<ul>';
					foreach ($errorsArray as $error) {
						$errors .= '<li>' . $error . '</li>';
					}
					$errors = '</ul>';
				}
				else {
					$errors = $errorsArray[0];
				}

				return new Response(json_encode(array(
					'errors' => $errors
				)));
			}
		}

		return $this->redirect($this->generateUrl('BNSAppBoardBundle_back'));
	}

	/**
	 * @Route("/information/{informationSlug}/alerte", name="board_manager_information_alert_switch")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function alertAction($informationSlug)
	{
		if ($this->getRequest()->isXmlHttpRequest()) {
			$context = $this->get('bns.right_manager')->getContext();
			$information = BoardInformationQuery::create('ba')
				->join('Board b')
				->where('b.GroupId = ?', $context['id'])
				->where('ba.Slug = ?', $informationSlug)
			->findOne();

			if (null == $information) {
				throw new NotFoundHttpException('The information with slug : ' . $informationSlug . ' is not found !');
			}

			$information->setIsAlert(!$information->isAlert());
			$information->save();

			return new Response();
		}

		return $this->redirect($this->generateUrl('BNSAppBoardBundle_back'));
	}

	/**
	 * @Route("/information/{informationId}/supprimer/confirmation/", name="board_manager_information_delete_confirm")
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function showDeleteInformationAction($informationId)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$information = BoardInformationQuery::create()
			->join('Board')
			->add(BoardPeer::GROUP_ID, $context['id'])
			->findPk($informationId);

		if (null == $information) {
			throw new NotFoundHttpException('The information with id : ' . $informationId . ' is NOT found !');
		}

		return $this->renderDeleteInformationModalAction($information);
	}

	/**
	 * @param \BNS\App\BoardBundle\Model\BoardInformation $information
	 *
	 * @return
	 */
	public function renderDeleteInformationModalAction(BoardInformation $information)
	{
		return $this->render('BNSAppBoardBundle:Modal:delete_layout.html.twig', array(
			'bodyValues'	=> array(
				'information' => $information,
			),
			'footerValues'	=> array(
				'information' => $information,
				'route'	 => $this->generateUrl('board_manager_information_delete', array('informationId' => $information->getId()))
			),
			'title'	=> $information->getTitle()
		));
	}

	/**
	 * @param \BNS\App\BoardBundle\Model\BoardRss $rss
	 *
	 * @return
	 */
	public function renderDeleteRssModalAction(BoardRss $rss)
	{
		return $this->render('BNSAppBoardBundle:Modal:delete_rss_layout.html.twig', array(
			'bodyValues'	=> array(
				'rss' => $rss,
			),
			'footerValues'	=> array(
				'rss' => $rss,
				'route'	 => $this->generateUrl('board_manager_rss_delete', array('id' => $rss->getId()))
			),
			'title'	=> $rss->getTitle()
		));
	}

	/**
	 * @Route("/information/{informationId}/supprimer", name="board_manager_information_delete")
	 *
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function deleteInformationAction($informationId)
	{
		$context = $this->get('bns.right_manager')->getContext();
		$query = BoardInformationQuery::create('ba')
			->join('Board')
			->where('Board.GroupId = ?', $context['id'])
			->where('ba.Id = ?', $informationId)
		;

		$information = $query->findOne();

		if (null == $information) {
			throw new NotFoundHttpException('The information with id : ' . $informationId . ' is NOT found !');
		}

		// Process
		$information->delete();

		if (!$this->getRequest()->isXmlHttpRequest()) {
			$this->get('session')->getFlashBag()->add('success', "L'information a été supprimée avec succès.");

			return $this->redirect($this->generateUrl('BNSAppBoardBundle_back'));
		}

		return new Response();
	}

	/**
	 * @Route("/commentaires/moderation", name="board_manager_moderation_switch")
	 * 
	 * @Rights("BOARD_ACCESS_BACK")
	 */
	public function switchModerationAction()
	{
		if (!$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page request must be AJAX !');
		}

		$context = $this->get('bns.right_manager')->getContext();
		$board = BoardQuery::create('b')
			->where('b.GroupId = ?', $context['id'])
		->findOne();

		if (null == $board) {
			throw new NotFoundHttpException('The board with group id ' . $context['id'] . ' is NOT found !');
		}

		$board->switchIsCommentModerate();
		$board->save();

		return new Response();
	}
}