<?php

namespace BNS\App\HomeworkBundle\Model;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\HomeworkBundle\Model\om\BaseHomeworkDue;

/**
 * Occurence d'un devoir.
 * Un devoir a des informations de recurrence; pour chaque occurence d'un devoir,
 * on cree un homeworkdue.
 *
 * @package    propel.generator.src.BNS.App.HomeworkBundle.Model
 */
class HomeworkDue extends BaseHomeworkDue
{

    public $done;

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

    /**
    * @return string 180 characters content
    */
    public function getShortDescription()
    {
        return $this->getHomework()->getShortDescription();
    }

    public function isDoneBy($user)
    {
        return HomeworkTaskQuery::create()->filterByUserId($user->getId())->filterByHomeworkDueId($this->getId())->count() > 0;
    }

    /**
     * @return array|User[]|\PropelObjectCollection
     */
    public function getUsersDone()
    {
        $userIds = [];
        foreach ($this->getHomeworkTasks() as $task) {
            if ($task->getDone()) {
                $userIds[] = $task->getUserId();
            }
        }

        return UserQuery::create()
            ->filterById($userIds)
            ->find()
        ;
    }

}
// HomeworkDue
