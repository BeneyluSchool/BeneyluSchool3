<?php

namespace BNS\App\HomeworkBundle\Homework;

use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkTask;
use BNS\App\HomeworkBundle\When\When;
use BNS\App\HomeworkBundle\Model\HomeworkTaskQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;

/**
 * Manager des devoirs pour le bundle Homework
 * Prend en charge le calcul des occurences de devoirs et des taches a affecter
 * aux membres des groupes concernes par un devoir donne.
 *
 * Utilise la BNSApi pour recuperer les groupes/membres de la Centrale
 * Utilise la librairie When pour les calculs d'occurences a partir d'infos de recurrence.
 */
class BNSHomeworkManager
{

    protected $api;

    protected $logger;

    protected $group_manager;

    /**
     * Constructeur du service BNSHomeworkManager.
     * Injection de la BNSApi pour faire des appels a la centrale.
     */
    public function __construct($api,$logger, $group_manager)
    {
        $this->api = $api;
        $this->logger = $logger;
        $this->group_manager = $group_manager;
    }

    /**
     * Calcule les occurences d'un devoir
     * @param Homework $homework un devoir
     */
    public function processHomework($homework)
    {
        if (!$homework->getRecurrenceType()) {
            $homework->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
        }
        if (!($homework->getRecurrenceDays() && count($homework->getRecurrenceDays()))) {
            $homework->setRecurrenceDays([strtoupper(substr($homework->getDate()->format("D"), 0, 2))]);
        }

        $due_dates = $this->computeDueDates($homework);
        $homeworkUserIds = $this->getAllHomeworkUserIds($homework);

        foreach ($due_dates as $date) {

            $hd = HomeworkDueQuery::create()
                    ->filterByHomework($homework)
                    ->filterByDueDate($date)
                    ->findOneOrCreate();

            if ($hd->isNew()) {
                // turn "Sun" into "SU"
                $hd->setDayOfWeek(strtoupper(substr($date->format("D"), 0, 2)));
                // mise à jour des nombres de tâches réalisées/total
                $hd->setNumberOfTasksDone(0);
                $hd->setHomework($homework);
                $hd->save();
            }

            // calcul des tâches
            $hd->setNumberOfTasksTotal(count($homeworkUserIds));

            if (!$hd->isNew()) {
                $tasks = $this->getTasksDone($hd, $homeworkUserIds);
                $hd->setNumberOfTasksDone($tasks->count());
            }
            $hd->save();
        }
    }

    /**
     * @param HomeworkDue $homeworkDue
     * @param array $userIds
     * @param bool $withUsers
     * @return HomeworkTask[]|\PropelObjectCollection
     */
    public function getTasksDone(HomeworkDue $homeworkDue, array $userIds = [], $withUsers = false)
    {
        if (!count($userIds)) {
            $userIds = $this->getAllHomeworkUserIds($homeworkDue->getHomework());
        }

        $tasks = HomeworkTaskQuery::create()
            ->filterByHomeworkDue($homeworkDue)
            ->filterByUserId($userIds, \Criteria::IN)
            ->filterByDone(true)
            ->_if($withUsers)
                ->joinWith('User')
            ->_endIf()
            ->find();

        return $tasks;
    }

    public function getUsersDone(HomeworkDue $homeworkDue, array $userIds = [])
    {
        $users = [];
        foreach ($this->getTasksDone($homeworkDue, $userIds, true) as $task) {
            $users[] = $task->getUser();
        }

        return $users;
    }

    /**
     * Gets all user ids related to the given homework, either by being in a group of the homework, or by being
     * assigned directly to the homework.
     *
     * @param Homework $homework
     * @return array
     */
    protected function getAllHomeworkUserIds(Homework $homework)
    {
        $ids = [];

        // pupils related by group
        foreach ($homework->getGroups() as $group) {
            foreach ($this->findMembersOfGroup($group) as $user) {
                $ids[] = $user['id'];
            }
        }

        // pupils assigned directly
        $ids = array_merge($ids, $homework->getUserIds());

        return array_unique($ids);
    }

    /*
     * Renvoie la liste des utilisateurs appartenant a un groupe donne
     * @param $group : le groupe en question
     * @return UserCollection : les utilisateurs renvoyés par l'API
     */

    protected function findMembersOfGroup($group)
    {
        $this->group_manager->setGroup($group);
        $result = $this->group_manager->getUsersByRoleUniqueName('PUPIL');

        return $result;
    }

    /*
     * Calcul des dates de homework dues en fonction
     * des données de récurrence pour un homework
     * @see https://github.com/tplaner/When
     * @see http://www.kanzaki.com/docs/ical/rrule.html
     */

    protected function computeDueDates($homework)
    {
        // toutes les due dates à calculer
        $due_dates = array();

        $recurrence_type = $homework->getRecurrenceType();
        $recurrence_days = $homework->getRecurrenceDays();
        $start_date = $homework->getDate();
        $end_date = $homework->getRecurrenceEndDate();

        if ($recurrence_type == HomeworkPeer::RECURRENCE_TYPE_ONCE) {

            $due_dates[] = $start_date;
        } else if ($recurrence_type == HomeworkPeer::RECURRENCE_TYPE_EVERY_WEEK) {

            $r = new When();
            $r->recur($start_date, 'weekly')
                    ->byday($recurrence_days)
                    ->until($end_date)
                    ->count(50);

            while ($result = $r->next()) {
                $due_dates[] = $result;
            }
        } else if ($recurrence_type == HomeworkPeer::RECURRENCE_TYPE_EVERY_TWO_WEEKS) {

            $r = new When();
            $r->recur($start_date, 'weekly')
                    ->interval(2)
                    ->byday($recurrence_days)
                    ->until($end_date)
                    ->count(50);

            while ($result = $r->next()) {
                $due_dates[] = $result;
            }
        } else if ($recurrence_type == HomeworkPeer::RECURRENCE_TYPE_EVERY_MONTH) {
            $r = new When();
            $r->recur($start_date, 'monthly')
                    ->bymonthday(array(intval($start_date->format('d'))))
                    ->until($end_date)
                    ->count(50);

            while ($result = $r->next()) {
                $due_dates[] = $result;
            }
        }
        return $due_dates;
    }

}
