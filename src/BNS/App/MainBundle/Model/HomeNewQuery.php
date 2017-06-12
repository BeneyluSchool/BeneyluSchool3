<?php

namespace BNS\App\MainBundle\Model;

use BNS\App\MainBundle\Model\om\BaseHomeNewQuery;

class HomeNewQuery extends BaseHomeNewQuery
{
    /**
     * Renvoie les dernières news pour un groupe donnée
     * @param $groupId
     * @return PropelCollection
     */
    public function getLastsByGroup($groupId)
    {
        return $this
            ->joinResource()
            ->filterByGroupId($groupId)
            ->orderByCreatedAt(\Criteria::DESC);
    }
}
