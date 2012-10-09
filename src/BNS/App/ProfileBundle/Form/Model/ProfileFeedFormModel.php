<?php

namespace BNS\App\ProfileBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\ProfileFeedStatus;
use BNS\App\CoreBundle\Form\Model\IFormModel;

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
	 * @param User $user
	 */
	public function __construct()
	{
		$this->user = BNSAccess::getUser();
		$this->date = time();
	}
	
	public function save()
	{
		$profileFeed = new ProfileFeedStatus();
		$profileFeed->getFeed()->setProfileId($this->user->getId());
		$profileFeed->getFeed()->setDate($this->date);
		$profileFeed->setContent($this->text);
		$profileFeed->setResourceId($this->resourceId);
			
		// Finally
		$profileFeed->save();
	}
}