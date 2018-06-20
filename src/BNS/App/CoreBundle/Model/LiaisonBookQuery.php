<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseLiaisonBookQuery;
use \Criteria;


/**
 * Skeleton subclass for performing query and update operations on the 'liaison_book' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class LiaisonBookQuery extends BaseLiaisonBookQuery {

    public function filterByGroupIdAndDate($groupId, $month, $year, $front)
    {
        if ($front) {
            $this->filterByPublicationDate(new \DateTime(), Criteria::LESS_EQUAL);
        }
        $this->filterByGroupId($groupId, \Criteria::EQUAL);
        $this->where("YEAR(". LiaisonBookPeer::DATE .") = ?", $year, \PDO::PARAM_INT);
        $this->where("MONTH(". LiaisonBookPeer::DATE .") = ?", $month, \PDO::PARAM_INT);
        $this->orderByDate(\Criteria::DESC);

        return $this;
    }

    public function filterByGroupIdAndLessOneYear($groupId)
    {
        $this->filterByGroupId($groupId);

        $date = date(mktime(0, 0, 0, date('n'), 1, date('y')-1));
        $this->filterByDate($date, \Criteria::GREATER_EQUAL);
        $this->orderByDate(Criteria::DESC);

        return $this;
    }

} // LiaisonBookQuery
