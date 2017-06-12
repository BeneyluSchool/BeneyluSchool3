<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseProfileCommentQuery;

use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for performing query and update operations on the 'profile_comment' table.

 *  * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class ProfileCommentQuery extends BaseProfileCommentQuery
{
	/**
	 * @param array $context
	 *
	 * @return ProfileCommentQuery
	 */
	public static function getBackComments($context)
	{
		$groupManager = BNSAccess::getContainer()->get('bns.group_manager');
		$groupManager->setGroupById($context['id']);
		$userIds = $groupManager->getUsersIds();

		return self::create('c')
			->joinWith('ProfileFeed')
			->joinWith('ProfileFeed.Profile')
			->join('Profile.User')
			->where('User.Id IN ?', $userIds)
		;
	}

	/**
	 * @param array $context
	 *
	 * @return boolean
	 */
	public static function isCommentModerate($context)
	{
		$groupManager = BNSAccess::getContainer()->get('bns.group_manager');
		$groupManager->setGroupById($context['id']);

		$pupilRole = GroupTypeQuery::create('g')
			->where('g.Type = ?', 'PUPIL')
		->findOne();

		return !in_array('PROFILE_NO_MODERATE_COMMENT', $groupManager->getPermissionsForRole($groupManager->getGroup(), $pupilRole));
	}
}
