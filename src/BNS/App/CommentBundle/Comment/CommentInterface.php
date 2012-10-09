<?php

namespace BNS\App\CommentBundle\Comment;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface CommentInterface
{
	/**
	 * @return User 
	 */
	public function getAuthor();
	
	/**
	 * @return int The object parent id
	 */
	public function getObjectId();
	
	/**
	 * @return ExtendedDatetime 
	 */
	public function getDate();
	
	/**
	 * @return string 
	 */
	public function getContent();
	
	/**
	 * The author of the object which the CommentBundle is linked
	 * For example, an article's author.
	 * 
	 * @return \BNS\App\CoreBundle\Model\User 
	 */
	public function getObjectAuthor();
	
	/**
	 * @return string The comment status
	 */
	public function getStatus();
	
	/**
	 * @return string The comment right to manage the comments
	 */
	public static function getCommentAdminRight();
}