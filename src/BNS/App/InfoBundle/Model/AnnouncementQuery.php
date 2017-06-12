<?php

namespace BNS\App\InfoBundle\Model;

use BNS\App\InfoBundle\Model\om\BaseAnnouncementQuery;

class AnnouncementQuery extends BaseAnnouncementQuery
{


    public function filterByActivated()
    {
        return $this->filterByIsActive(1);
    }

    public function filterByTypeCustom()
    {
        return $this->filterByType(AnnouncementPeer::TYPE_CUSTOM);
    }


}
