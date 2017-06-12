<?php

namespace BNS\App\CommentBundle\Form\Model;

use BNS\App\CommentBundle\Comment\CommentInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class CommentForm
{
	/**
	 * @var int 
	 */
	public $id;
	
	/**
	 * @var string
	 */
	public $content;
	
	/**
	 * @var int 
	 */
	public $author_id;
	
	/**
	 * @var int 
	 */
	public $object_id;
	
	/**
	 * @var CommentInferface
	 */
	private $comment;
	
	
	/**
	 * @param \BNS\App\CommentBundle\Form\Model\CommentInterface $comment
	 */
	public function __construct(CommentInterface $comment = null)
	{
		if (null != $comment) {
			$this->author_id = $comment->getAuthorId();
			$this->content   = $comment->getContent();
			$this->id		 = $comment->getId();
			$this->object_id = $comment->getObjectId();
		}
	}
	
	/**
	 * @param string $namespace The comment class namespace
	 */
	public function save($namespace)
	{
		$queryClass = $namespace . 'Query';
		$this->comment = $queryClass::create('c')->findPk($this->id);
		$this->comment->setContent($this->content);
		$this->comment->save();
	}
	
	/**
	 * @return CommentInterface
	 */
	public function getComment()
	{
		return $this->comment;
	}
}