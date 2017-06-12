<?php

namespace BNS\App\WorkshopBundle\Model;

use BNS\App\WorkshopBundle\Model\om\BaseWorkshopDocumentQuery;

class WorkshopDocumentQuery extends BaseWorkshopDocumentQuery
{

    public function makeJoin($userId = null)
    {
        $query = $this->useResourceQuery();

        if($userId != null)
        {
            $query->filterByUserId($userId);
        }
        return $query->useUserQuery()->endUse()->endUse();
    }



}
