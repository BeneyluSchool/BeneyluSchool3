<?php

namespace BNS\App\LiaisonBookBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearLiaisonBookDataReset extends AbstractDataReset
{
    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_liaison_book';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        LiaisonBookQuery::create('lb')
            ->where('lb.GroupId = ?', $group->getId())
            ->where('lb.Date <= ?', time())
        ->delete();
    }
}