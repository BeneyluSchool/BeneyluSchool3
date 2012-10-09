<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfile;


/**
 * Skeleton subclass for representing a row from the 'profile' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class Profile extends BaseProfile
{
	public function replaceProfileFeeds($feeds)
	{
		$this->collProfileFeeds = $feeds;
	}

	public function getUser()
	{
		$user = isset($this->collUsers[0])? $this->collUsers[0] : null;
		if ($user == null) {
			$users = $this->getUsers();
			$user = $users[0];
		}
		
		return $user;
	}

	public function isFilled()
	{
		return null != $this->getUser()->getBirthday() && null != $this->getDescription() && null != $this->getJob();
	}
	
	/**
	 * @param type $user
	 */
	public function replaceUser($user)
	{
		$this->collUsers[0] = $user;
	}
}