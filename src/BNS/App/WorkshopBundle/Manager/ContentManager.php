<?php

namespace BNS\App\WorkshopBundle\Manager;

use BNS\App\CoreBundle\Date\DateI18n;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Manager\MediaCreator;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\Model\WorkshopAudio;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributor;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributor;
use BNS\App\WorkshopBundle\Model\WorkshopContentInterface;
use BNS\App\WorkshopBundle\Model\WorkshopContentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;

/**
 * Class ContentManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class ContentManager
{

    /**
     * @var RightManager
     */
    private $rightManager;

    /**
     * @var MediaCreator
     */
    private $mediaCreator;

    /**
     * @var DateI18n
     */
    private $dateI18n;

    public function __construct(RightManager $rightManager, MediaCreator $mediaCreator, DateI18n $dateI18n)
    {
        $this->rightManager = $rightManager;
        $this->mediaCreator = $mediaCreator;
        $this->dateI18n = $dateI18n;
    }

    /**
     * @param WorkshopContent $content
     * @param array|User[] $users
     */
    public function setContributorUsers(WorkshopContent $content, $users)
    {
        $userIds = array();
        foreach ($users as $user) {
            $userIds[] = $user->getId();
        }

        $this->setContributorUserIds($content, $userIds);
    }

    /**
     * Sets the given user ids as contributors.
     * Returns an array of statistics, having the following keys:
     *  - previous: array of previously set user ids
     *  - added:    array of user ids that have been added
     *  - removed:  array of user ids that have been removed
     *
     * @param WorkshopContent $content
     * @param array $userIds
     * @return array
     */
    public function setContributorUserIds(WorkshopContent $content, $userIds = array())
    {
        $stats = array(
            'added' => array(),
            'removed' => array(),
        );
        $stats['previous'] = $currentIds = $this->getContributorUserIds($content);

        $toRemove = array_diff($currentIds, $userIds);  // ids in current but not in new
        $toAdd = array_diff($userIds, $currentIds);     // ids in new but not in current

        // remove old ids
        /** @var WorkshopContentContributor[]|\PropelObjectCollection $contributors */
        $contributors = $content->getWorkshopContentContributors();
        foreach ($contributors as $contributor) {
            $id = $contributor->getUserId();
            if (in_array($id, $toRemove)) {
                $contributor->delete();
                $stats['removed'][] = $id;
            }
        }

        // if objects were removed, update the collection to remove cached objects
        foreach ($toRemove as $id) {
            foreach ($contributors as $key => $contributor) {
                if ($contributor->getUserId() === $id) {
                    $contributors->remove($key);
                    break;
                }
            }
        }

        // add new ids
        foreach ($toAdd as $newId) {
            $contributor = new WorkshopContentContributor();
            $contributor->setUserId($newId);
            $contributor->setWorkshopContent($content);
            $stats['added'][] = $newId;
        }

        return $stats;
    }

    /**
     * @param WorkshopContent $content
     * @param array|Group[] $groups
     */
    public function setContributorGroups(WorkshopContent $content, $groups)
    {
        $groupIds = array();
        foreach ($groups as $group) {
            $groupIds[] = $group->getId();
        }

        $this->setContributorGroupIds($content, $groupIds);
    }

    /**
     * @param WorkshopContent $content
     * @param array $groupIds
     */
    public function setContributorGroupIds(WorkshopContent $content, $groupIds = array())
    {
        $currentIds = $this->getContributorGroupIds($content);

        $toRemove = array_diff($currentIds, $groupIds);  // ids in current but not in new
        $toAdd = array_diff($groupIds, $currentIds);     // ids in new but not in current

        // remove old ids
        /** @var WorkshopContentGroupContributor[]|\PropelObjectCollection $contributors */
        $contributors = $content->getWorkshopContentGroupContributors();
        foreach ($contributors as $contributor) {
            $id = $contributor->getGroupId();
            if (in_array($id, $toRemove)) {
                $contributor->delete();
            }
        }

        // if objects were removed, update the collection to remove cached objects
        foreach ($toRemove as $id) {
            foreach ($contributors as $key => $contributor) {
                if ($contributor->getGroupId() === $id) {
                    $contributors->remove($key);
                    break;
                }
            }
        }

        // add new ids
        foreach ($toAdd as $newId) {
            $contributor = new WorkshopContentGroupContributor();
            $contributor->setGroupId($newId);
            $contributor->setWorkshopContent($content);
        }

        $content->setWorkshopContentGroupContributors($content->getWorkshopContentGroupContributors());
    }

    /**
     * @param WorkshopContent $content
     * @return array|User[]
     */
    public function getContributorUsers(WorkshopContent $content)
    {
        $users = array();
        foreach ($content->getWorkshopContentContributorsJoinUser() as  $contributor) {
            $users[] = $contributor->getUser();
        }

        return $users;
    }

    /**
     * @param WorkshopContent $content
     * @return array
     */
    public function getContributorUserIds(WorkshopContent $content)
    {
        $userIds = array();
        foreach ($content->getWorkshopContentContributors() as  $contributor) {
            $userIds[] = $contributor->getUserId();
        }

        return $userIds;
    }

    /**
     * @param WorkshopContent $content
     * @return array|Group[]
     */
    public function getContributorGroups(WorkshopContent $content)
    {
        $groups = array();
        foreach ($content->getWorkshopContentGroupContributorsJoinGroup() as  $contributor) {
            $groups[] = $contributor->getGroup();
        }

        return $groups;
    }

    /**
     * @param WorkshopContent $content
     * @return array
     */
    public function getContributorGroupIds(WorkshopContent $content)
    {
        $groupIds = array();
        if($content){
            foreach ($content->getWorkshopContentGroupContributors() as  $contributor) {
                $groupIds[] = $contributor->getGroupId();
            }
        }

        return $groupIds;
    }

    /**
     * Checks if the given User is contributor of the given document
     *
     * @param WorkshopContent $content
     * @param User $user
     * @return bool
     */
    public function isContributor(WorkshopContent $content, User $user)
    {
        if ($content->getAuthorId() === $user->getId()) {
            return true;
        }

        foreach ($content->getWorkshopContentContributors() as $contributor) {
            if ($contributor->getUserId() === $user->getId()) {
                return true;
            }
        }

        $userGroups = $user->getGroups();
        foreach ($content->getWorkshopContentGroupContributors() as $contributorGroup) {
            foreach ($userGroups as $userGroup) {
                if ($userGroup->getId() === $contributorGroup->getGroupId()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if given user can manage the given content
     *
     * @param WorkshopContent $content
     * @param User $user
     * @return bool
     */
    public function canManage(WorkshopContent $content, User $user)
    {
        // check if user is author, a contributor, or in a contributor group
        if ($this->isContributor($content, $user)) {
            return true;
        } else {
            // check if user can manage the author of the document
            $managedUserIds = $this->rightManager->getManagedAuthorIds($user);

            return in_array($content->getAuthor()->getId(), $managedUserIds);
        }
    }

    /**
     * Makes a flat copy of the given WorkshopContent and returns it.
     * Also copies contributor Users and Groups by default. They can be overriden with the given arrays of  user ids and
     * group ids. If boolean false is given instead, contributors are not copied.
     *
     * @param WorkshopContent $content
     * @param bool|array $contributorUserIds An array of user ids to override contributor users. If boolean false, copy
     *                                       of contributors is disabled. If anything else, original contributors are
     *                                       used.
     * @param bool|array $contributorGroupIds Same as above, for groups.
     * @return WorkshopContent
     */
    public function copy(WorkshopContent $content, $contributorUserIds = null, $contributorGroupIds = null)
    {
        $newContent = $content->copy();

        // copy or override contributor users
        if (false !== $contributorUserIds) {
            if (!is_array($contributorUserIds)) {
                $contributorUserIds = $this->getContributorUserIds($content);
            }
            $this->setContributorUserIds($newContent, $contributorUserIds);
        }

        // copy or override contributor groups
        if (false !== $contributorGroupIds) {
            if (!is_array($contributorGroupIds)) {
                $contributorGroupIds = $this->getContributorGroupIds($content);
            }
            $this->setContributorGroupIds($newContent, $contributorGroupIds);
        }

        return $newContent;
    }

    /**
     * Sets up the given WorkshopContentInterface along with its WorkshopContent, Media, sets sensible defaults, and
     * returns it. It is *not* saved.
     *
     * @param WorkshopContentInterface $document
     * @param User $user
     * @param Media $media
     * @return WorkshopContentInterface
     */
    public function setup(WorkshopContentInterface $document, User $user = null, Media $media = null)
    {
        if ($document->getWorkshopContent()) {
            throw new \InvalidArgumentException("Cannot setup with existing WorkshopContent");
        }

        // create a new WorkshopContent
        $document->setWorkshopContent(new WorkshopContent());
        if ($document instanceof WorkshopDocument) {
            $document->getWorkshopContent()->setType(WorkshopContentPeer::TYPE_DOCUMENT);
        } else if ($document instanceof WorkshopAudio) {
            $document->getWorkshopContent()->setType($type = WorkshopContentPeer::TYPE_AUDIO);
        } else {
            throw new \InvalidArgumentException("Unknown WorkshopContent class '".get_class($document)."'");
        }

        if (!$user) {
            $user = $this->mediaCreator->getUserManager()->getUser();
        }

        if ($user->isChild()) {
            $name = $user->getFirstName() . ' ' . mb_substr($user->getLastName(), 0, 1);
        } else {
            $name = $user->getFullName();
        }

        $date = $this->dateI18n->process(new \DateTime(), 'short', 'none', 'd LLLL');

        // create a new Media
        if (!$media) {
            $media = $this->mediaCreator->createModelDatas(array(
                'label' => $date . ' - ' . $name,
                'media_folder' => $user->getMediaFolderRoot(),
                'user_id' => $user->getId(),
                'mime_type' => 'NONE',
            ));
        }
        $media->setTypeUniqueName('ATELIER_'.$document->getType());

        $document->getWorkshopContent()->setMedia($media);
        $document->getWorkshopContent()->setAuthor($user);

        return $document;
    }

}
