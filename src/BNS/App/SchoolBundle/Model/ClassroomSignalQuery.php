<?php

namespace BNS\App\SchoolBundle\Model;

use BNS\App\SchoolBundle\Model\om\BaseClassroomSignalQuery;

class ClassroomSignalQuery extends BaseClassroomSignalQuery
{
    public function getActiveSignalQuery()
    {
        return $this
                ->filterByStatus(false)
                ->orderByCreatedAt();
    }

}
