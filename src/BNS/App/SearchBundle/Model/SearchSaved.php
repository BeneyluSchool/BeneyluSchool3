<?php

namespace BNS\App\SearchBundle\Model;

use BNS\App\SearchBundle\Model\om\BaseSearchSaved;

class SearchSaved extends BaseSearchSaved
{

    public function printCreatedAt()
    {
        return $this->getCreatedAt('Y-m-d H:i:s');
    }

}
