<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopWidgetGroupQuery;

class WorkshopWidgetGroupQuery extends BaseWorkshopWidgetGroupQuery
{

    public function makeJoin()
    {
        return $this->useWorkshopWidgetQuery()->makeJoin()->endUse();
    }

}
