<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkQuery;

class HomeworkQuery extends BaseHomeworkQuery
{

    public function filterByPublicationStatus($status)
    {
        if (!is_array($status)) {
            $status = [$status];
        }

        $scheduled = in_array('SCHED', $status);
        $published = in_array('PUB', $status);

        if ($published && $scheduled) {
            // both filters <=> no filter
            return $this;
        } else if ($published) {
            $this
                ->filterByPublicationDate(new \DateTime(), \Criteria::LESS_EQUAL)
                ->_or()
                ->filterByScheduledPublication(false)
            ;
        } else if ($scheduled) {
            $this
                ->filterByScheduledPublication(true)
                ->filterByPublicationDate(new \DateTime(), \Criteria::GREATER_THAN)
            ;
        }

        return $this;
    }

}
