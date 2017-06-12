<?php

namespace BNS\App\HomeworkBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkDue;

/**
 * Class BaseHomeworkApiController
 *
 * @package BNS\App\HomeworkBundle\ApiController
 */
class BaseHomeworkApiController extends BaseApiController
{

    protected function canManageGroup(Group $group)
    {
        return $this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $group->getId());
    }

    protected function canManageHomework(Homework $homework)
    {
        return $this->get('bns.right_manager')->hasRightInSomeGroups(
            'HOMEWORK_ACCESS_BACK',
            $homework->getGroupsIds()
        );
    }

    protected function canManageHomeworkDue(HomeworkDue $homeworkDue)
    {
        return $this->canManageHomework($homeworkDue->getHomework());
    }

}
