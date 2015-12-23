<?php

namespace BNS\App\ReservationBundle\Model;

use BNS\App\ReservationBundle\Model\om\BaseReservationItem;

class ReservationItem extends BaseReservationItem
{
    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getTitle();
    }

    public function getColorClass()
    {
        if (ReservationItemPeer::TYPE_ROOM == $this->getType()) {
            return 'cal-green';
        }

        return 'cal-blue';
    }
}
