<?php

namespace BNS\App\CalendarBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\AbstractDataReset;
use BNS\App\CoreBundle\Model\AgendaEventQuery;
use BNS\App\CoreBundle\Model\AgendaQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ChangeYearCalendarDataReset extends AbstractDataReset
{
    /**
     * @return string 
     */
    public function getName()
    {
        return 'change_year_calendar';
    }

    /**
     * @param Group $group
     */
    public function reset($group)
    {
        $calendarId = AgendaQuery::create('a')
            ->select('a.Id')
        ->findOneByGroupId($group->getId());

        AgendaEventQuery::create('ae')
            ->where('ae.AgendaId = ?', $calendarId)
            ->where('ae.DateStart <= ?', time())
        ->delete();
    }
}