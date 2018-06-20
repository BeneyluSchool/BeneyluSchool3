<?php

namespace BNS\App\CommentBundle\Controller;

use BNS\App\CommentBundle\Form\Model\CommentForm;
use BNS\App\CoreBundle\Model\BlogArticleCategoryPeer;
use BNS\App\CoreBundle\Model\BlogArticleCommentPeer;
use BNS\App\CoreBundle\Model\BlogArticleCommentQuery;
use BNS\App\CoreBundle\Utils\Crypt;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
	public function showAction($namespace = null, $editRoute = null, $page = 1, $status = 'PENDING_VALIDATION', $material = true, $feedId = false, $display = false)
	{
		if ($this->getRequest()->isMethod('POST')) {
			$namespace	= $this->getRequest()->get('object_namespace');
			$page		= $this->getRequest()->get('pageR',$page);
			$status		= $this->getRequest()->get('object_status');
			$editRoute	= $this->getRequest()->get('edit_route');
			$feedId     = $this->getRequest()->get('feed_id');
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
		->paginate($page, 10);

		if($feedId != false){
			$pager = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
					->where('c.Status = ?', $status)
					->where('c.ObjectId = ?', $feedId)
					->orderBy('c.Date', \Criteria::DESC)
			->paginate($page, 1000);
		}
		if($material == true){
			return $this->render('BNSAppCommentBundle:BackNew:comment_list.html.twig', array(
				'comments'	=> $pager->getResults(),
				'pager'		=> $pager,
				'status'	=> $status,
				'with_multiselect' => true,
				'editRoute'	=> $editRoute,
				'display' => $display
			));
		}

		return $this->render('BNSAppCommentBundle:Back:comment_list.html.twig', array(
			'comments'	=> $pager->getResults(),
			'pager'		=> $pager,
			'status'	=> $status,
			'editRoute'	=> $editRoute
		));
	}

	/**
	 * @Route("/visualiser", name="comment_manager_visualize")
	 */
	public function visualizeAction($objectId, $namespace, $editRoute, $material = false)
	{
		if (!class_exists($namespace)) {
			throw new \InvalidArgumentException('The namespace that you provide : ' . $namespace . ' does NOT exist !');
		}

		$queryClass = $namespace . 'Query';

		// Fetch comments
		$comments = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.ObjectId = ?', $objectId)
			->orderBy('c.Date', \Criteria::ASC)
		->find();

		if($material == true){
			return $this->render('BNSAppCommentBundle:BackNew:comment_visualisation.html.twig', array(
					'comments'	=> $comments,
					'namespace'	=> Crypt::encrypt($namespace),
					'editRoute'	=> $editRoute,
			));
		}
		return $this->render('BNSAppCommentBundle:Back:comment_visualisation.html.twig', array(
			'comments'	=> $comments,
			'namespace'	=> Crypt::encrypt($namespace),
			'editRoute'	=> $editRoute
		));
	}

	/**
	 * @Route("/status", name="comment_manager_status_update", options={"expose"=true})
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
		$editRoute	= $this->getRequest()->get('editRoute', null);

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

		$namespace	 = Crypt::decrypt($namespace);
		$queryClass	 = $namespace . 'Query';
		$peerClass	 = $namespace . 'Peer';

		$rights		 = $namespace::getCommentAdminRight();
		$isDenied	 = false;
		if (is_array($rights)) {
			$isDenied = !$this->get('bns.right_manager')->hasRights($rights);
		}
		else {
			$isDenied = !$this->get('bns.right_manager')->hasRight($rights);
		}

		if ($isDenied) {
			throw new AccessDeniedHttpException('You have NOT the permission to access this page !');
		}

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
			$commentModeration = $this->renderView('BNSAppCommentBundle:BackNew:comment_moderation.html.twig', array(
				'comment'   => $comment,
				'editRoute'	=> $editRoute
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
			$commentHtml = $this->renderView('BNSAppCommentBundle:BackNew:comment_row.html.twig', array(
				'comment'	=> $comment,
				'editRoute'	=> $editRoute
			));
		}

		// Generate the pager
		$pager = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Status = ?', $lastStatus)
		->paginate($page);

		if (0 == $pager->count()) {
			$commentHtml = $this->renderView('BNSAppCommentBundle:BackNew:comment_empty.html.twig');
		}

		$pagerHtml = $this->renderView('BNSAppCommentBundle:BackNew:comment_pager.html.twig', array(
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

		$comments = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Status = ?', 'PENDING_VALIDATION')
		->find();

		foreach ($comments as $comment) {
			$comment->setStatus('VALIDATED');
		}

		$comments->save();

		return new Response();
	}

	/**
	 * @param int    $id
	 * @param string $namespace
	 *
	 * @return Response
	 *
	 * @throws NotFoundHttpException
	 */
	public function renderEditCommentAction($id, $namespace, $extendsView, $isModeration, $form, $extraParams = array())
	{
		$queryClass = $namespace . 'Query';

		// Fetch comments
		$comment = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
			->where('c.Id = ?', $id)
		->findOne();

		if (null == $comment) {
			throw new NotFoundHttpException('The comment with id : ' . $id . ' is NOT found or denied !');
		}

		if (!$form->isBound()) {
			$form->setData(new CommentForm($comment));
		}

		return $this->render('BNSAppCommentBundle:Back:comment_form.html.twig', array_merge($extraParams, array(
			'comment'	   => $comment,
			'extendsView'  => $extendsView,
			'form'		   => $form->createView(),
			'isModeration' => $isModeration
		)));
	}

    /**
     * @Route("/tout-supprimer", name="comment_manager_delete_all")
     */
    public function deleteAllAction()
    {
        if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
            throw new NotFoundHttpException('The page excepts POST & AJAX header !');
        }

        $namespace	= $this->getRequest()->get('namespace', null);

        // Check parameters
        if (null == $namespace) {
            throw new \InvalidArgumentException('There is some missing mandatory inputs !');
        }

        if (!$this->get('bns.right_manager')->hasRightSomeWhere('BLOG_ADMINISTRATION')) {
            throw $this->createAccessDeniedException();
        }

        $namespace	= Crypt::decrypt($namespace);
        $queryClass	= $namespace . 'Query';
        $peerClass	= $namespace . 'Peer';

        $comments = $queryClass::getBackComments($this->get('bns.right_manager')->getContext())
            ->where('c.Status = ?', 'REFUSED')
            ->find()->delete();

        return new Response();
    }
}
