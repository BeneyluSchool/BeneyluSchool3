<?php

namespace BNS\App\ProfileBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Form\Model\IFormModel;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;
use BNS\App\CoreBundle\Model\ProfileFeedStatus;
use BNS\App\CoreBundle\Model\User;

class ProfileFeedFormModel implements IFormModel
{
	/**
	 * @var User 
	 */
	protected $user;
	
	/**
	 * @var long|string|DateTime 
	 */
	protected $date;
	
	/**
	 * @var string 
	 */
	public $text;
	
	/**
	 * @var ?
	 */
	public $resourceId;
	
	/**
	 * @var ProfileFeed 
	 */
	private $profileFeed;
	
	/**
	 * @param User $user
	 */
	public function __construct($feed = null)
	{
		if (null != $feed) {
			$this->user = $feed->getProfileFeed()->getAuthor();
			$this->date	= $feed->getProfileFeed()->getDate();
			$this->text	= $feed->getContent();
			$this->profileFeed = $feed;
		}
		else {
			$this->user = BNSAccess::getUser();
			$this->date = time();
			$this->profileFeed = new ProfileFeedStatus();
		}
	}
	
	public function save($isModerated = true, $isAdmin = false)
	{
		$this->profileFeed->getFeed()->setProfileId($this->user->getProfileId());
		$this->profileFeed->getFeed()->setDate($this->date);
		$this->profileFeed->setContent($this->text);
		$this->profileFeed->setResourceId($this->resourceId);
		
		// Automatic validation for new feed & for admin
		if (!$isModerated || $isAdmin) {
			$this->profileFeed->getFeed()->setStatus(ProfileFeedPeer::STATUS_VALIDATED);
		}
		// Finally
		$this->profileFeed->save();
	}
	
	/**
	 * @return ProfileFeed 
	 */
	public function getFeed()
	{
		return $this->profileFeed->getFeed();
	}
}