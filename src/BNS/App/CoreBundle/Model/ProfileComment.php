<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileComment;
use BNS\App\CommentBundle\Comment\CommentInterface;


/**
 * Skeleton subclass for representing a row from the 'profile_comment' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileComment extends BaseProfileComment implements CommentInterface
{
	public function __construct()
	{
		$this->applyDefaultValues();
		$this->setDate(time());
	}
	
	/**
	 * Simple shortcut
	 * 
	 * @param PropelPDO $con
	 * 
	 * @return User 
	 */
	public function getAuthor(\PropelPDO $con = null)
	{
		return $this->getUser($con);
	}
	
	/**
	 * Simple shortcut
	 *
	 * @param User $author 
	 */
	public function setAuthor(User $author)
	{
		$this->setUser($author);
	}
	
	/**
	 * @return User
	 */
	public function getObjectAuthor()
	{
		return $this->getProfileFeed()->getAuthor();
	}
	
	/**
	 * @return string
	 */
	public static function getCommentAdminRight()
	{
		return 'PROFILE_ADMINISTRATION';
	}
}