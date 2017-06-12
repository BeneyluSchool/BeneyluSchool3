<?php

namespace BNS\App\HomeworkBundle\Homework;

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
     * @param type $homework un devoir
     */
    public function processHomework($homework)
    {

        $due_dates = $this->computeDueDates($homework);

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

                // calcul des tâches
                $task_count = 0;
                foreach ($homework->getGroups() as $group) {
                    $task_count += $this->generateHomeworkTasksForGroup($hd, $group);
                }

                $hd->setNumberOfTasksTotal($task_count);
                $hd->save();
            } else {
                $task_count = 0;
                foreach ($homework->getGroups() as $group) {
                    $task_count += $this->generateHomeworkTasksForGroup($hd, $group);
                }

                $hd->setNumberOfTasksTotal($task_count);
                $hd->save();
            }
        }
    }

    /**
     * Calcule les taches affectees aux membres d'un groupe
     * concerne par une occurence d'un devoir
     * @param type $homeworkdue une occurence de devoir
     * @param type $group un groupe concerne par ce devoir
     * @return int nombre de taches creees pour ce groupe
     */
    protected function generateHomeworkTasksForGroup($homeworkdue, $group)
    {
        $users = $this->findMembersOfGroup($group);
        $task_count = count($users);

        return $task_count;
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
