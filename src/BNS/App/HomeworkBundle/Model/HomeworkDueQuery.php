<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\CoreBundle\Model\User;
use BNS\App\HomeworkBundle\Model\om\BaseHomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkGroupQuery;
use \Criteria;
use \DateTime;
use \DateInterval;

/**
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkDueQuery extends BaseHomeworkDueQuery
{

    protected $daysInWeek = array('SU' => 'sunday', 'MO' => 'monday', 'TU' => 'tuesday', 'WE' => 'wednesday', 'TH' => 'thursday', 'FR' => 'friday', 'SA' => 'saturday');

    /**
     * Trouve la prochaine occurence (a partir de maintenant)
     * du devoir passe en parametre
     *
     * @param type $homeworkId id du homework concerne
     */
    public function findNextOccuringHomeworkDue($homeworkId)
    {
        $interval = array('min' => 'now');

        return $this->filterByHomeworkId($homeworkId)
                ->filterByDueDate($interval)
                ->orderByDueDate(Criteria::ASC)
                ->findOne();
    }

    /*
     * Trouve les HomeworkDues :
     * - sur une période donnée (entre "start_day" et "end_day")
     * - qui sont assignés à un des groupes donnés
     * - join group
     * - join homework
     */

    public function findForRangeForGroups($start_day, $end_day, $groups, $subjects_ids = null, $groups_id = null, $days_ids = null)
    {
        $interval = array('min' => $start_day, 'max' => $end_day);

        $hgQuery = HomeworkGroupQuery::create()
                ->filterByGroup($groups);
        if($groups_id != null)
        {
            $hgQuery->filterByGroupId($groups_id, Criteria::IN);
        }
        $homeworkgroups = $hgQuery->find();


        $query = $this->filterByDueDate($interval);
        if ($days_ids != null )
        {
            $query = $query->filterByDayOfWeek($days_ids, Criteria::IN);
        }
        $query = $query->useHomeworkQuery()
                ->filterByHomeworkGroup($homeworkgroups, Criteria::IN);

        if ($subjects_ids != null)
        {
            $query = $query->filterBySubjectId($subjects_ids, Criteria::IN);
        }

        $query = $query->endUse()
                ->joinHomework()
                ->distinct()
                ->orderByDueDate();

        return $query->find();
    }

    public function filterByRangeAndSubject($startDay, $endDay, $subjectIds = null, $dayIds = null)
    {
        $this->filterByDueDate([
            'min' => $startDay,
            'max' => $endDay
        ]);

        if ($dayIds) {
            $this->filterByDayOfWeek($dayIds, Criteria::IN);
        }

        if ($subjectIds) {
            $this
                ->useHomeworkQuery()
                    ->filterBySubjectId($subjectIds, Criteria::IN)
                ->endUse()
            ;
        }

        return $this;
    }

    /**
     * @param array $userIds
     * @return $this
     */
    public function filterByUserIds(array $userIds)
    {
        $this
            ->useHomeworkQuery()
                ->useHomeworkUserQuery()
                    ->filterByUserId($userIds, \Criteria::IN)
                ->endUse()
            ->endUse()
        ;

        return $this;
    }

    /**
     * @param array $groupIds
     * @return $this
     */
    public function filterByGroupIds(array $groupIds)
    {
        $this
            ->useHomeworkQuery()
                ->useHomeworkGroupQuery()
                    ->filterByGroupId($groupIds, \Criteria::IN)
                ->endUse()
            ->endUse()
        ;

        return $this;
    }

    /**
     * @param $status
     * @return \ModelCriteria|$this
     */
    public function filterByPublicationStatus($status)
    {
        return $this
            ->useHomeworkQuery()
                ->filterByPublicationStatus($status)
            ->endUse()
        ;
    }


    /**
     * Trouve les HomeworkDues pour une date donnée
     *
     * @param array $groupIds groupd Ids where user has HOMEWORK_ACCESS permission
     * @param $day
     * @return array|mixed|\PropelObjectCollection
     */
    public function findForDay(array $groupIds, $day)
    {
        $homeworkDues =
            $this->filterByDueDate($day)
                ->useHomeworkQuery()
                    ->joinHomeworkSubject()
                    ->useHomeworkGroupQuery()
                        ->filterByGroupId($groupIds, \Criteria::IN)
                    ->endUse()
                ->endUse()
                ->find();

        return $homeworkDues;
    }

    /**
     * @deprecated should be removed security issue instead use findPastForGroups()
     *
     * Trouve les HomeworkDues qui:
     * - qui sont terminés (ou non) pour un user donné
     * - pour un jour de la semaine donné
     * - avec des options de pagination
     */
    public function findFuturesForDayOfWeek($user, $dayOfWeek, $page = 1, $daysPerPage = 4)
    {
        //create interval given pagination data
        $minDate = new DateTime();
        $minDate->setTimestamp(strtotime($this->daysInWeek[$dayOfWeek] . ' this week'));
        $minDate->add(new DateInterval('P' . (1 + ($page - 1) * $daysPerPage) . 'D'));

        $maxDate = new DateTime();
        $maxDate->setTimestamp(strtotime($this->daysInWeek[$dayOfWeek] . ' this week'));
        $maxDate->add(new DateInterval('P3M'));

        $interval = array('min' => $minDate->format("Y-m-d"), 'max' => $maxDate->format("Y-m-d"));

        $homeworkDues = $this->filterByDueDate($interval)
            ->filterByDayOfWeek($dayOfWeek)
            ->useHomeworkQuery()
                ->filterByGroup($user->getGroups(), \Criteria::IN)
                ->joinHomeworkSubject()
            ->endUse()
            ->orderByDueDate()
            ->find();

        return $homeworkDues;
    }

    /**
     * @deprecated should be removed security issue instead use findPastForGroups()
     *
     * Trouve les HomeworkDues qui:
     * - qui sont dans le passé
     * - avec des options de pagination
     */
    public function findPast($user, $page = 1, $itemsPerPage = 5)
    {
        $today = new DateTime();
        $interval = array('max' => $today);

        $pager = $this->filterByDueDate($interval)
                ->useHomeworkQuery()
                ->filterByGroup($user->getGroups(), \Criteria::IN)
                ->joinHomeworkSubject()
                ->endUse()
                ->orderByDueDate(Criteria::DESC)
                ->paginate($page, $itemsPerPage);

        return array($pager->getResults(), $pager);
    }

    /*
     * Trouve les HomeworkDues qui:
     * - pour des groupes donnés
     * - qui sont dans le passé
     * - avec des options de pagination
     */

    public function findPastForGroups($groups, $page = 1, $itemsPerPage = 5)
    {
        $today = new DateTime();
        $interval = array('max' => $today);

        $pager = $this->filterByDueDate($interval)
                ->useHomeworkQuery()
                ->filterByGroup($groups, Criteria::IN)
                ->joinHomeworkSubject()
                ->endUse()
                ->orderByDueDate(Criteria::DESC)
                ->distinct()
                ->paginate($page, $itemsPerPage);

        return array($pager->getResults(), $pager);
    }

    public function findPastForGroupIds($groupIds, $page = 1, $itemsPerPage = 5)
    {
        if (!is_array($groupIds)) {
            $groupIds = array($groupIds);
        }
        $today = new DateTime();
        $interval = array('max' => $today);

        $pager = $this->filterByDueDate($interval)
            ->useHomeworkQuery()
                ->useHomeworkGroupQuery()
                    ->filterByGroupId($groupIds, Criteria::IN)
                ->endUse()
                ->joinHomeworkSubject()
            ->endUse()
            ->orderByDueDate(Criteria::DESC)
            ->distinct()
            ->paginate($page, $itemsPerPage);

        return array($pager->getResults(), $pager);
    }

    /*
     * Trouve les HomeworkDues qui:
     * - qui sont terminés (ou non) pour un user donné
     * - pour un jour de la semaine donné
     * - avec des options de pagination
     */

    public function findFuturesForDayOfWeekForGroup($groups, $dayOfWeek, $page = 1, $daysPerPage = 4)
    {
        //create interval given pagination data
        $minDate = new DateTime();
        $minDate->setTimestamp(strtotime($this->daysInWeek[$dayOfWeek] . ' this week'));
        $minDate->add(new DateInterval('P' . (1 + ($page - 1) * $daysPerPage) . 'D'));

        $maxDate = new DateTime();
        $maxDate->setTimestamp(strtotime($this->daysInWeek[$dayOfWeek] . ' this week'));
        $maxDate->add(new DateInterval('P' . ($page) * 7 * $daysPerPage . 'D'));

        $interval = array('min' => $minDate->format("Y-m-d"), 'max' => $maxDate->format("Y-m-d"));

        $homeworkDues = $this->filterByDueDate($interval)
            ->useHomeworkQuery()
                ->useHomeworkGroupQuery()
                    ->filterByGroupId($groups, Criteria::IN)
                ->endUse()
                ->joinHomeworkSubject()
            ->endUse()
            ->filterByDayOfWeek($dayOfWeek)
            ->orderByDueDate()
            ->find();

        return $homeworkDues;
    }

}
