<?php

namespace BNS\App\NoteBookBundle\Model;

use BNS\App\NoteBookBundle\Model\om\BaseNoteBookQuery;

class NoteBookQuery extends BaseNoteBookQuery
{
    public function filterByMonthAndYear($month, $year)
    {
        return $this->filterByMonth($month)->filterByYear($year);
    }

    public function filterByMonth($month)
    {
        $this->where("MONTH(". NoteBookPeer::DATE .") = ? ", $month, \PDO::PARAM_INT);

        return $this;
    }

    public function filterByYear($year)
    {
        $this->where("YEAR(". NoteBookPeer::DATE .") = ? ", $year, \PDO::PARAM_INT);

        return $this;
    }

    public function filterByLessOneYear()
    {
        $date = date(mktime(0, 0, 0, date('n'), 1, date('y')-1));
        $this->filterByDate($date, \Criteria::GREATER_EQUAL);

        return $this;
    }

    public function findDistinctMonth()
    {
        return $this->distinct()
            ->withColumn("DATE_FORMAT(" . NoteBookPeer::DATE . ",'%Y-%m-01')", 'month')
            ->orderBy('month', \Criteria::DESC)
            ->select('month')
            ->find();
    }
}
