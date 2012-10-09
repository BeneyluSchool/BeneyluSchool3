<?php

namespace BNS\App\CommentBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Utils\Crypt;

/**
 * @Route("/gestion")
 * 
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class BackCommentController extends Controller
{
	/**
	 * @Route("/charger", name="comment_manager_load")
	 */
	public function showAction($namespace = null, $page = 1, $status = 'PENDING_VALIDATION')
	{
		if ($this->getRequest()->isMethod('POST')) {
			$namespace	= $this->getRequest()->get('namespace');
			$page		= $this->getRequest()->get('page');
			$status		= $this->getRequest()->get('status');
		}
		
		$namespace = Crypt::decrypt($namespace);
		if (!class_exists($namespace)) {
			throw new \InvalidArgumentException('The namespace that you provide : ' . $namespace . ' does NOT exist !');
		}
		
		$queryClass = $namespace . 'Query';
		$peerClass = $namespace . 'Peer';
		
		// Check if status exsits
		$statuses = $peerClass::getValueSet($peerClass::STATUS);
		if (!in_array($status, $statuses)) {
			throw new \InvalidArgumentException('The status : ' . $status . ' does NOT exist !');
		}
		
		// Fetch comments
		$pager = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Status = ?', $status)
			->orderBy('c.Date', \Criteria::DESC)
		->paginate($page);
		
		return $this->render('BNSAppCommentBundle:Back:comment_list.html.twig', array(
			'comments'	=> $pager->getResults(),
			'pager'		=> $pager,
			'status'	=> $status
		));
	}
	
	/**
	 * @Route("/visualiser", name="comment_manager_visualize")
	 */
	public function visualizeAction($objectId, $namespace)
	{
		if (!class_exists($namespace)) {
			throw new \InvalidArgumentException('The namespace that you provide : ' . $namespace . ' does NOT exist !');
		}
		
		$queryClass = $namespace . 'Query';
		
		// Fetch comments
		$comments = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.ObjectId = ?', $objectId)
			->orderBy('c.Date', \Criteria::DESC)
		->find();
		
		return $this->render('BNSAppCommentBundle:Back:comment_visualisation.html.twig', array(
			'comments'	=> $comments,
			'namespace'	=> Crypt::encrypt($namespace)
		));
	}
	
	/**
	 * @Route("/status", name="comment_manager_status_update")
	 */
	public function updateStatusAction($isVisualisation = false)
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$namespace	= $this->getRequest()->get('namespace', null);
		$id			= $this->getRequest()->get('id', null);
		$status		= $this->getRequest()->get('status', null);
		$page		= $this->getRequest()->get('page', null);
		
		// Check parameters
		if (!$isVisualisation) {
			if (null == $namespace || null == $id || null == $status || null == $page) {
				throw new \InvalidArgumentException('There is some missing mandatory inputs !');
			}
		}
		else {
			if (null == $namespace || null == $id || null == $status) {
				throw new \InvalidArgumentException('There is some missing mandatory inputs !');
			}
		}
		
		$namespace	= Crypt::decrypt($namespace);
		$queryClass	= $namespace . 'Query';
		$peerClass	= $namespace . 'Peer';
		
		$comment = $queryClass::create('c')
			->where('c.Id = ?', $id)
		->findOne();
		
		if (null == $comment) {
			throw new NotFoundHttpException('The comment with id ' . $id . ' is NOT found !');
		}
		
		// Check if status exsits
		$statuses = $peerClass::getValueSet($peerClass::STATUS);
		if (!in_array($status, $statuses)) {
			throw new \InvalidArgumentException('The status : ' . $status . ' does NOT exist !');
		}
		
		$lastStatus = $comment->getStatus();
		$comment->setStatus($status);
		$comment->save();
		
		if ($isVisualisation) {
			$commentModeration = $this->renderView('BNSAppCommentBundle:Back:comment_moderation.html.twig', array(
				'comment' => $comment,
			));
			
			return new Response(json_encode(array(
				'moderation'	=> $commentModeration,
				'classe'		=> strtolower($comment->getStatus())
			)));
		}
		
		// Show one comment
		$comment = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Status = ?', $lastStatus)
			->orderBy('c.Date', \Criteria::DESC)
			->offset(10)
		->findOne();

		$commentHtml = null;
		if (null != $comment) {
			$commentHtml = $this->renderView('BNSAppCommentBundle:Back:comment_row.html.twig', array(
				'comment'	=> $comment
			));
		}
		
		// Generate the pager
		$pager = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Status = ?', $lastStatus)
		->paginate($page);
		
		if (0 == $pager->count()) {
			$commentHtml = $this->renderView('BNSAppCommentBundle:Back:comment_empty.html.twig');
		}
		
		$pagerHtml = $this->renderView('BNSAppCommentBundle:Back:comment_pager.html.twig', array(
			'pager'	 => $pager,
			'status' => $lastStatus
		));
		
		return new Response(json_encode(array(
			'comment'	=> $commentHtml,
			'pager'		=> $pagerHtml
		)));
	}
	
	/**
	 * @Route("/status/visualisation", name="comment_manager_status_update_visualisation")
	 */
	public function updateStatusVisualisationAction()
	{
		return $this->updateStatusAction(true);
	}
	
	/**
	 * @Route("/tout-valider", name="comment_manager_validate_all")
	 */
	public function validateAllAction()
	{
		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('The page excepts POST & AJAX header !');
		}
		
		$namespace	= $this->getRequest()->get('namespace', null);
		
		// Check parameters
		if (null == $namespace) {
			throw new \InvalidArgumentException('There is some missing mandatory inputs !');
		}
		
		$namespace	= Crypt::decrypt($namespace);
		$queryClass	= $namespace . 'Query';
		$peerClass	= $namespace . 'Peer';
		
		$comments = $queryClass::create('c')
			->where('c.Status = ?', 'PENDING_VALIDATION')
		->find();
		
		foreach ($comments as $comment) {
			$comment->setStatus('VALIDATED');
		}
		
		$comments->save();
		
		return new Response();
	}
}