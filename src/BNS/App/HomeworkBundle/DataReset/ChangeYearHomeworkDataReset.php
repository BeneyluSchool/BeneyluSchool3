<?php

namespace BNS\App\HomeworkBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\HomeworkBundle\Model\HomeworkGroupQuery;
use BNS\App\HomeworkBundle\Model\HomeworkQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearHomeworkDataReset extends AbstractDataReset
{
    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_homework';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        $homeworksId = HomeworkGroupQuery::create('hg')
            ->select('hg.HomeworkId')
        ->findByGroupId($group->getId());

        HomeworkQuery::create('h')
            ->where('h.Id IN ?', $homeworksId)
            ->where('h.date <= ?', time('Y-m-d'))
        ->delete();
    }
}