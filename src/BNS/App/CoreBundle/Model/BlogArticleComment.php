<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseBlogArticleComment;
use BNS\App\CommentBundle\Comment\CommentInterface;

class BlogArticleComment extends BaseBlogArticleComment implements CommentInterface
{
	public function __construct()
	{
		$this->applyDefaultValues();
		$this->setDate(time());
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @return User The author
	 */
	public function getAuthor()
	{
		return $this->getUser();
	}
	
	public function __toString()
	{
		return 'bla';
	}
	
	/**
	 * @return User 
	 */
	public function getObjectAuthor()
	{
		return $this->getBlogArticle()->getAuthor();
	}
	
	/**
	 * @return string
	 */
	public static function getCommentAdminRight()
	{
		return 'BLOG_ADMINISTRATION';
	}
}