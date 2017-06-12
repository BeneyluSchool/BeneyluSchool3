<?php

namespace BNS\App\ProfileBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\User\DataResetUserInterface;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\CoreBundle\Model\ProfileQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearProfileDataReset implements DataResetUserInterface
{
    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_profile';
    }

    /**
     * @param array $usersId
     */
    public function reset($usersId)
    {

        /*
         * Eymeric le 05/06/2015 : On ne supprime plus les profils
         * $profileFeedsId = ProfileQuery::create('p')
            ->select('p.Id')
            ->join('p.User u')
            ->where('u.Id IN ?', $usersId)
        ->find();

        ProfileFeedQuery::create('pf')
            ->where('pf.ProfileId IN ?', $profileFeedsId)
        ->delete();*/
    }
}