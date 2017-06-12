<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopPageQuery;

class WorkshopPageQuery extends BaseWorkshopPageQuery
{

    public static function create($modelAlias = null, $criteria = null)
    {
        // order by rank, by default
        return parent::create($modelAlias, $criteria)
            ->orderByPosition()
        ;
    }

    public function makeJoin()
    {
        return $this->useWorkshopWidgetGroupQuery()->makeJoin()->endUse();
    }
}
