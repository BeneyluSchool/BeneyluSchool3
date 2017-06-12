<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkPreferences;

/**
 * Preferences de l'application Homework:
 * - jours consideres pour l'affichage
 * - affichage ou non du statut fait/a faire des taches
 * 
 * Les preferences associees a un groupe.
 * 
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkPreferences extends BaseHomeworkPreferences {

    public function getDaysSorted()
    {
        $order = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
        $days = $this->getDays();

        return array_values(array_intersect($order, $days));
    }

} // HomeworkPreferences
