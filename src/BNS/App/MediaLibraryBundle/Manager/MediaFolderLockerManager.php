<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;

/**
 * Class MediaFolderLockerManager
 *
 * @package BNS\App\MediaLibraryBundle\Manager
 */
class MediaFolderLockerManager
{

    /**
     * @var MediaFolderManager
     */
    protected $mediaFolderManager;

    /**
     * @var BNSRightManager
     */
    protected $rightManager;

    public function __construct(MediaFolderManager $mediaFolderManager, BNSRightManager $rightManager)
    {
        $this->mediaFolderManager = $mediaFolderManager;
        $this->rightManager = $rightManager;
    }

    /**
     * @param Homework $homework
     */
    public function createForHomework(Homework $homework)
    {
        foreach ($homework->getGroups() as $group) {
            $folder = MediaFolderGroupQuery::create()
                ->filterByGroup($group)
                ->filterByHomework($homework)
                ->findOne();

            if ($folder) {
                if ($folder->getStatusDeletion() === MediaFolderManager::STATUS_GARBAGED) {
                    $this->mediaFolderManager->setMediaFolderObject($folder);
                    $this->mediaFolderManager->restore();
                }
            } else {
                $label = array();

                /** @var ExtendedDateTime $date */
                $date = $homework->getDate();
                $formatter = \IntlDateFormatter::create(
                    '',
                    \IntlDateFormatter::LONG,
                    \IntlDateFormatter::NONE,
                    $date->getTimezone()->getName(),
                    \IntlDateFormatter::GREGORIAN,
                    'EEEE d MMMM'
                );
                $label[] = ucfirst($formatter->format($date->getTimestamp()));

                if ($homework->getSubjectId()) {
                    $label[] = $homework->getHomeworkSubject()->getName();
                }

                $label[] = $homework->getName();

                $parent = $this->mediaFolderManager->getGroupFolder($group);
                $folder = $this->mediaFolderManager->create(implode(' - ', $label), $parent->getId(), $parent->getType());
                $folder->setIsLocker(true);
                $folder->setHomework($homework);
                $folder->save();
            }
        }
    }

    /**
     * TODO check that user can insert media
     *
     * @param Homework $homework
     * @param null|int|Group $group
     * @return MediaFolderGroup
     *
     * @throws \PropelException
     */
    public function getLockerForHomework(Homework $homework, $group = null)
    {
        $groupId = 0;

        // group given, get its ID
        if ($group) {
            if ($group instanceof Group) {
                $groupId = $group->getId();
            } else {
                $groupId = (int)$group;
            }
        }

        // no group given, try to guess the best match
        if (!$groupId) {
            $homeworkGroupIds = $homework->getGroupsIds();
            $userGroupIds = array_intersect(
                $this->rightManager->getGroupIdsWherePermission('HOMEWORK_ACCESS'),
                $this->rightManager->getGroupIdsWherePermission('MEDIA_LIBRARY_ACCESS')
            );

            // prefer the current group, if available
            $currentGroupId = $this->rightManager->getCurrentGroupId();
            if (in_array($currentGroupId, $homeworkGroupIds) && in_array($currentGroupId, $userGroupIds)) {
                $groupId = $currentGroupId;
            }

            // fallback to the list of user group, get the first in common
            if (!$groupId) {
                foreach ($userGroupIds as $id) {
                    if (in_array($id, $homeworkGroupIds)) {
                        $groupId = $id;
                        break;
                    }
                }
            }
        }

        return MediaFolderGroupQuery::create()
            ->filterByIsLocker(true)
            ->filterByHomework($homework)
            ->filterByGroupId($groupId)
            ->filterByStatusDeletion(MediaManager::STATUS_ACTIVE)
            ->findOne()
        ;
    }

}
