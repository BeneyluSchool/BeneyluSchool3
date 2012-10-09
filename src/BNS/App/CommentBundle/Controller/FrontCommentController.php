<?php

namespace BNS\App\CommentBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CommentBundle\Comment\CommentInterface;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\CommentBundle\Form\Model\CommentForm;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontCommentController extends Controller
{
	/**
	 * Called by Twig render function
	 * 
	 * @param array $comments
	 * @param boolean $ajaxLoading
	 * @param int $limit
	 * @return \Symfony\Component\HttpFoundation\Response 
	 * 
	 * @throws \InvalidArgumentException
	 */
    public function showAction($namespace, $objectId, array $comments, $nbComments, $ajaxLoading = false, $limit = 5)
    {
		if (count($comments) > 0) {
			if (!$comments[0] instanceof CommentInterface) {
				throw new \InvalidArgumentException('The comment must implement the IComment interface !');
			}
		}
		
		$nbComments = null == $nbComments ? 0 : $nbComments;
		
		return $this->render('BNSAppCommentBundle:Comment:comment_index.html.twig', array(
			'need_ajax'				=> $ajaxLoading && $nbComments > $limit,
			'comments'				=> $comments,
			'nb_comments'			=> $nbComments,
			'object_id'				=> $objectId,
			'admin_right'			=> $namespace::getCommentAdminRight(),
			'namespace'				=> Crypt::encrypt($namespace)
		));
    }
	
	/**
	 * @Route("/charger", name="comment_load")
	 */
	public function loadAction()
	{
		if (!$this->getRequest()->isMethod('POST') || null == $this->getRequest()->get('object_id', null)) {
			throw new NotFoundHttpException();
		}
		
		if (null == $this->getRequest()->get('namespace', null)) {
			throw new \InvalidArgumentException('The comment object namespace must be specified !');
		}
		
		$namespace = Crypt::decrypt($this->getRequest()->get('namespace'));
		$queryClass = $namespace . 'Query';
		$peerClass = $namespace . 'Peer';
		
		$totalComments = $this->getRequest()->get('total_comments', null);
		if (null == $totalComments) {
			throw new \InvalidArgumentException('The comment count must be specified !');
		}
		
		$comments = $queryClass::create()
			->add($peerClass::OBJECT_ID, $this->getRequest()->get('object_id'))
			->addAscendingOrderByColumn($peerClass::DATE)
			->joinWith('User')
			->limit($totalComments - $this->getRequest()->get('nb_comments', 0))
		->find();
		
		return $this->render('BNSAppCommentBundle:Comment:comment_list.html.twig', array(
			'comments'		=> $comments,
			'admin_right'	=> $namespace::getCommentAdminRight(),
		));
	}
	
	/**
	 * @Route("/ajouter", name="comment_add")
	 */
	public function addAction()
	{
		if (!$this->getRequest()->isXmlHttpRequest() || 'POST' != $this->getRequest()->getMethod()) {
			throw new NotFoundHttpException();
		}
		
		if (null == $this->getRequest()->get('namespace', null)) {
			throw new \InvalidArgumentException('The object namespace must be specified !');
		}
		
		$namespace	= Crypt::decrypt($this->getRequest()->get('namespace'));
		$comment	= null;
		
		$commentForm = new CommentForm();
		$commentForm->author_id	= $this->getUser()->getId();
		$commentForm->object_id	= (int) $this->getRequest()->get('object_id', null);
		$commentForm->content	= $this->getRequest()->get('content', null);
		
		$errors = $this->get('validator')->validate($commentForm);
		$error = null;
		
		$adminRight = $namespace::getCommentAdminRight();
		
		if (isset($errors[0])) {
			$error = $errors[0]->getMessage();
			$html = '';
		}
		else {
			$comment = new $namespace();
			$comment->setAuthorId($this->getUser()->getId());
			$comment->setUser($this->getUser());
			$comment->setObjectId($this->getRequest()->get('object_id'));
			$comment->setContent($this->getRequest()->get('content'));
			
			$queryClass = $namespace . 'Query';
			if ($this->get('bns.right_manager')->hasRight($adminRight) || !$queryClass::isCommentModerate($this->get('bns.right_manager')->getContext())) {
				$comment->setStatus('VALIDATED');
			}
			
			$comment->save();
			
			$html = $this->renderView('BNSAppCommentBundle:Comment:comment_row.html.twig', array(
				'comment'		=> $comment,
				'admin_right'	=> $adminRight
			));
		}
		
		return new Response(json_encode(array(
			'error' => $error,
			'html'	=> $html
		)));
	}
}