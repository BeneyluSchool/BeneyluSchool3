<?php

namespace BNS\App\ClassroomBundle\Model;

use BNS\App\ClassroomBundle\Model\om\BaseClassroomPushQuery;

class ClassroomPushQuery extends BaseClassroomPushQuery
{

    public function getCurrent()
    {
        return $this->filterByFromDate(time(), \Criteria::LESS_EQUAL)
            ->filterByToDate(time(), \Criteria::GREATER_EQUAL)
            ->orderByFromDate(\Criteria::DESC)
            ->findOne();
    }

}
