<?php

namespace BNS\App\HomeworkBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
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
        $rightManager = $this->get('bns.right_manager');
        // check for group's homework
        if ($rightManager->hasRightInSomeGroups(
            'HOMEWORK_ACCESS_BACK',
            $homework->getGroupsIds()
        )) {
            return true;
        }

        $groupManager = $this->get('bns.group_manager');
        // check for user's homework
        if (count($userIds = $homework->getUserIds()) > 0) {
            $groupIds = $rightManager->getGroupIdsWherePermission('HOMEWORK_ACCESS_BACK');
            $pupilRoleId = (int) GroupTypeQuery::create()
                ->filterBySimulateRole(true)
                ->filterByType('PUPIL')
                ->select('Id')
                ->findOne()
            ;
            foreach ($groupIds as $groupId) {
                $pupilIds = $groupManager->getUserIdsByRole($pupilRoleId, $groupId);
                if (count(array_intersect($userIds, $pupilIds)) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function canManageHomeworkDue(HomeworkDue $homeworkDue)
    {
        return $this->canManageHomework($homeworkDue->getHomework());
    }

}
