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

    protected function getCurrentBlog()
    {
        if(!isset($this->currentBlog))
        {
            $this->currentBlog = $this->get('bns.right_manager')->getCurrentGroup()->getBlog();
        }
        return $this->currentBlog;
    }

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
    public function showAction($namespace, $objectId, array $comments, $ajaxLoading = false, $limit = 10, $material = true)
    {
        $nbComments = $this->getNbComments($namespace . 'Query', $objectId, $this->get('bns.right_manager')->hasRight($namespace::getCommentAdminRight()));

		if($material == true){
			return $this->render('BNSAppCommentBundle:CommentNew:comment_index.html.twig', array(
					'need_ajax'				=> $ajaxLoading && $nbComments > $limit,
					'comments'				=> $comments,
					'nb_comments'			=> $nbComments,
					'object_id'				=> $objectId,
					'admin_right'			=> $namespace::getCommentAdminRight(),
					'namespace'				=> Crypt::encrypt($namespace)
			));
		}
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
     * Called by Twig render function for rendering into PDF
     *
     * @param array $comments
     * @param boolean $ajaxLoading
     * @param int $limit
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \InvalidArgumentException
     */
    public function showPdfAction($namespace, $objectId, array $comments, $ajaxLoading = false, $limit = 10)
    {
        $nbComments = $this->getNbComments($namespace . 'Query', $objectId, $this->get('bns.right_manager')->hasRight($namespace::getCommentAdminRight()));

        return $this->render('BNSAppCommentBundle:Comment:comment_pdf_index.html.twig', array(
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
		
		$query = $queryClass::create('c')
			->joinWith('User')
			->joinWith('User.Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->where('c.ObjectId = ?', $this->getRequest()->get('object_id'))
			->orderBy('c.Date', \Criteria::DESC)
			->offset($this->getRequest()->get('nb_comments', 0));
		;
		
		// Has admin right ? Show moderate comments
		if ($this->get('bns.right_manager')->hasRight($namespace::getCommentAdminRight())) {
			$query->where('c.Status != ?', 'REFUSED');
		}
		else {
			$query->where('c.Status = ?', 'VALIDATED')
				  ->orWhere('c.AuthorId = ?', $this->getUser()->getId())
				  ->where('c.Status != ?', 'REFUSED')
			;
		}

        if($queryClass == '\BNS\App\CoreBundle\Model\BlogArticleCommentQuery')
        {
            $query->filterByBlog($this->getCurrentBlog());
        }
		
		$comments = $query->find();
		
		return $this->render('BNSAppCommentBundle:CommentNew:comment_list.html.twig', array(
			'comments'		=> $comments,
			'admin_right'	=> $namespace::getCommentAdminRight()
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

            //Cas particulier pour le blog : on ajoute le blog ID

            if(method_exists($comment,'setBlogId'))
            {
                $comment->setBlogId($this->get('bns.right_manager')->getCurrentGroup()->getBlog()->getId());
            }

			$comment->setContent($this->getRequest()->get('content'));
			
			$queryClass = $namespace . 'Query';
			if ($this->get('bns.right_manager')->hasRight($adminRight) || !$queryClass::isCommentModerate($this->get('bns.right_manager')->getContext())) {
				$comment->setStatus('VALIDATED');
			}
			
			$comment->save();
            
            //Statistic action
            if(preg_match("/BlogArticleComment/", $namespace)) {
                $this->get("stat.blog")->newCommentArticle();
                $this->get('snc_redis.default')->del('yerbook_height_' . $this->getRequest()->get('object_id'));
            } else if(preg_match("/ProfileComment/", $namespace)) {
                $this->get("stat.profile")->newComment();
            }
			
			$html = $this->renderView('BNSAppCommentBundle:CommentNew:comment_row.html.twig', array(
				'comment'		=> $comment,
				'admin_right'	=> $adminRight,
				'onlyValidated' => false
			));
		}
		
		return new Response(json_encode(array(
			'error' => $error,
			'html'	=> $html
		)));
	}
	
	/**
	 * @param string  $queryClass
	 * @param int	  $feedId
	 * @param boolean $hasAdminRights
	 * 
	 * @return int 
	 */
	private function getNbComments($queryClass, $feedId, $hasAdminRights)
	{
		$query = $queryClass::create('c');
		if ($hasAdminRights) {
			$query->where('c.Status != ?', 'REFUSED');
		}
		else {
			$query->where('c.Status = ?', 'VALIDATED')
				  ->orWhere('c.AuthorId = ?', $this->getUser()->getId())
				  ->where('c.Status != ?', 'REFUSED')
			;
		}
		
		$query->where('c.ObjectId = ?', $feedId);

        if($queryClass == '\BNS\App\CoreBundle\Model\BlogArticleCommentQuery')
        {
            $query->filterByBlog($this->getCurrentBlog());
        }

		return $query->count();
	}
}