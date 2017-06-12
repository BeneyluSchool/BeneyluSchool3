<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidgetQuery;

class WorkshopWidgetQuery extends BaseWorkshopWidgetQuery
{

    public function makeJoin()
    {
        return $this->useResourceQuery()->endUse();
    }
}
