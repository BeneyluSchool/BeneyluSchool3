<?php

namespace BNS\App\CoreBundle\Model;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use BNS\App\CoreBundle\Model\om\BasePupilParentLinkPeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\PupilParentLink;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;

class PupilParentLinkPeer extends BasePupilParentLinkPeer
{

    /**
     * @deprecated
     * @see BNSUserManager::addParent()
     * @param \BNS\App\CoreBundle\Model\User $pupil
     * @param \BNS\App\CoreBundle\Model\User $parent
     */
	public static function createPupilParentLink(User $pupil, User $parent)
	{
		if (null == $pupil || null == $parent) {
			throw new InvalidArgumentException('You must provide parameters $user != null && $parent != null!');
		}
		
		$pupilParentLink = new PupilParentLink();
		$pupilParentLink->setUserPupilId($pupil->getId());
		$pupilParentLink->setUserParentId($parent->getId());
		
		// Finally
		$pupilParentLink->save();
	}

    /**
     * @deprecated
     * @see BNSUserManager::addParent()
     * @param \BNS\App\CoreBundle\Model\User $pupil
     * @param \BNS\App\CoreBundle\Model\User $parent
     */
        public static function removePupilParentLink(User $pupil, User $parent)
	{
                if (null == $pupil || null == $parent) {
			throw new InvalidArgumentException('You must provide parameters $user != null && $parent != null!');
		}
                
                $pupilParentLink = PupilParentLinkQuery::create()
			->filterByUserPupilId($pupil->getId())
                        ->filterByUserParentId($parent->getId())
                        ->findOne();
                
                // Finally
                $pupilParentLink->delete();
        }
}
