<?php

namespace BNS\App\HomeworkBundle\Homework;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\HomeworkBundle\Model\HomeworkDue;

/**
 * Class BNSHomeworkDueManager
 *
 * @package BNS\App\HomeworkBundle\Homework
 */
class BNSHomeworkDueManager
{

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    public function __construct(BNSUserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * If given User has children, get those who have done the given HomeworkDue
     *
     * @param HomeworkDue $homeworkDue
     * @param User $parent
     * @return array|User[]
     */
    public function getChildrenDone(HomeworkDue $homeworkDue, User $parent)
    {
        if ($this->userManager->hasChild($parent)) {
            $doneBy = [];

            foreach ($this->userManager->getUserChildren($parent) as $child) {
                if ($homeworkDue->isDoneBy($child)) {
                    $doneBy[] = $child;
                }
            }

            return $doneBy;
        }

        return null;
    }

}
