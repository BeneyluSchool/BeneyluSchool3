<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\HomeworkBundle\Model\om\BaseHomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkTaskQuery;

/**
 * Occurence d'un devoir.
 * Un devoir a des informations de recurrence; pour chaque occurence d'un devoir,
 * on cree un homeworkdue.
 *
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkDue extends BaseHomeworkDue
{

    /**
     * Mise a jour du nombre de taches realisees par les eleves
     * pour cette occurence de devoir.
     */
    public function updateNumberOfTasksDone()
    {
        $count = HomeworkTaskQuery::create()
                ->filterByHomeworkDueId($this->id)
                ->filterByDone(true)
                ->count();
        
        $this->setNumberOfTasksDone($count);
        $this->save();
    }

}
// HomeworkDue
