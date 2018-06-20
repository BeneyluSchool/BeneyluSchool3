<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\PupilParentLinkQuery;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\HomeworkBundle\Model\HomeworkQuery;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Model\om\BaseMediaFolderGroup;

class MediaFolderGroup extends BaseMediaFolderGroup
{
    use TranslatorTrait;

    const LABEL_GENERAL_FOLDER = 'LABEL_GENERAL_FOLDER';

    public static $hydrateChildrenFolder = true;

    protected $medias;

    protected $marker;

    protected $uniqueKey;

    protected $role;

    protected $isEmpty;

    /**
     * @inheritDoc
     */
    public function postHydrate($row, $startcol = 0, $rehydrate = false)
    {
        parent::postHydrate($row, $startcol, $rehydrate);

        $this->setupForSpecialFolder();
    }

    /**
     * Setup object properly if it's a special folder
     */
    public function setupForSpecialFolder()
    {
        if ($this->getIsExternalFolder()) {
            $this->role = 'external';

            $translator = $this->getTranslator();
            if ($translator) {
                $this->label = $this->getTranslator()->trans('LABEL_SPOT_FOLDER', [], 'MEDIA_LIBRARY');
            }
        }
    }

    public function getLabel()
    {
        if ($this->getIsUserFolder()) {
            $translator = $this->getTranslator();
            if (!$translator) {
                return self::LABEL_GENERAL_FOLDER;
            }

            /** @Ignore */
            return $translator->trans(self::LABEL_GENERAL_FOLDER, array(), 'MEDIA_LIBRARY');
        }

        return parent::getLabel();
    }

    /**
     * Renvoie le type de dossier pour les traitements globaux sur les médias folder
     * @return string GROUP forcément
     */
    public function getType()
    {
        return 'GROUP';
    }

    public function getOwnerId()
    {
        return $this->getGroupId();
    }

    public function setMedias($medias)
    {
        $this->medias = $medias;
    }

    /**
     * Renvoie les medias actifs associés au dossier
     * @return array|mixed|Media[]|\PropelObjectCollection
     */
    public function getMedias()
    {
        if (isset($this->medias)) {
            return $this->medias;
        }

        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getMedias();
    }

    public function getGroupType($force = false)
    {
        if(0 === $this->getLevel() || $force) {
            if ($this->hasVirtualColumn('groupType')) {
                return $this->getVirtualColumn('groupType');
            }

            return $this->getGroup()->getType();
        }

        return null;
    }

    public function getChildren($criteria = null, \PropelPDO $con = null, $withUserFolders = true, $withSpotFolder = false)
    {
        $groupType = $this->getGroupType(true);
        if ($groupType !== 'CLASSROOM') {
            $withUserFolders = false;
        }

        if (!$this->getIsUserFolder() || $groupType !== 'CLASSROOM') {
            $container = BNSAccess::getContainer();
            $criteria = MediaFolderGroupQuery::create();
            $criteria->add(MediaFolderGroupPeer::STATUS_DELETION,MediaFolderManager::STATUS_ACTIVE);
            if (!$withUserFolders) {
                $criteria->add(MediaFolderGroupPeer::IS_USER_FOLDER,false);
            }
            $withPrivate = $container->get('bns.media_library_right.manager')->canManageFolder($this);
            if (!$withPrivate) {
               $criteria->add(MediaFolderGroupPeer::IS_PRIVATE,false);
            }

            // can't see content => add an impossible condition, to have an empty collection
            if (!$container->get('bns.media_library_right.manager')->canReadFolderContent($this)) {
                $criteria->where('1 <> 1');
                // skip unnecessary code
                goto get_parent_children_label;
            }

            // keep only visible locker folders
            $rm = $container->get('bns.right_manager');
            $canManageHomework = $rm->hasRight('HOMEWORK_ACCESS_BACK', $this->getGroupId());
            if (!$canManageHomework) {
                $userIds = [$rm->getUserSessionId()];
                $childrenIds = PupilParentLinkQuery::create()
                    ->filterByUserParentId($rm->getUserSessionId())
                    ->useUserRelatedByUserPupilIdQuery()
                        ->filterByArchived(false)
                    ->endUse()
                    ->select(['UserPupilId'])
                    ->find()
                    ->getArrayCopy()
                ;
                $userIds = array_merge($userIds, $childrenIds);

                $publishedHomeworkIds = HomeworkQuery::create()
                    ->filterByHasLocker(true)
                    ->filterByPublicationStatus('PUB')
                    ->useMediaFolderGroupQuery(null, \Criteria::INNER_JOIN)
                        ->filterByGroupId($this->getGroupId())
                        ->filterByIsLocker(true)
                    ->endUse()
                    ->select(['Id'])
                    ->find()
                    ->getArrayCopy()
                ;
                $criteria->condition('not_homework', MediaFolderGroupPeer::HOMEWORK_ID.' IS NULL');
                $criteria->condition('published_homework', MediaFolderGroupPeer::HOMEWORK_ID.' IN ?', $publishedHomeworkIds);
                $criteria->combine(['not_homework', 'published_homework'], \Criteria::LOGICAL_OR);

                $userHomeworkIds = HomeworkQuery::create()
                    ->filterByHasLocker(true)
                    ->useMediaFolderGroupQuery(null, \Criteria::INNER_JOIN)
                        ->filterByGroupId($this->getGroupId())
                        ->filterByIsLocker(true)
                    ->endUse()
                    ->useHomeworkUserQuery()
                        ->filterByUserId($userIds, \Criteria::IN)
                    ->endUse()
                    ->select(['Id'])
                    ->find()
                    ->getArrayCopy()
                ;
                $criteria->condition('not_locker', MediaFolderGroupPeer::IS_LOCKER.' = ?', false);
                $criteria->condition('locker', MediaFolderGroupPeer::IS_LOCKER.' = ?', true);
                $criteria->condition('locker2', MediaFolderGroupPeer::IS_LOCKER.' = ?', true);
                $criteria->condition('individual', MediaFolderGroupPeer::HOMEWORK_INDIVIDUAL.' = ?', true);
                $criteria->condition('not_individual', MediaFolderGroupPeer::HOMEWORK_INDIVIDUAL.' = ?', false);
                $criteria->condition('visible_homework', MediaFolderGroupPeer::HOMEWORK_ID.' IN ?', $userHomeworkIds);
                $criteria->combine(['locker', 'individual', 'visible_homework'], \Criteria::LOGICAL_AND, 'visible_user_locker');
                $criteria->combine(['locker2', 'not_individual'], \Criteria::LOGICAL_AND, 'visible_group_locker');
                $criteria->combine(['not_locker', 'visible_group_locker', 'visible_user_locker'], \Criteria::LOGICAL_OR);
            }

            if ($withSpotFolder && 0 === $this->getLevel() && in_array($groupType, ['CLASSROOM', 'SCHOOL'])) {
                if (0 === MediaFolderGroupQuery::create()
                    ->filterByGroupId($this->getGroupId())
                    ->filterByIsExternalFolder(true)
                    ->count()) {
                    $this->getGroup()->getExternalFolder(); // refresh spot folder
                }
            }

            get_parent_children_label:
            $children = parent::getChildren($criteria, $con);

            return $children;
        }

        // TODO : Remove BnsAccess Call
        //Recupération des dossiers users
        $gm = BNSAccess::getContainer()->get('bns.group_manager');
        $rm = BNSAccess::getContainer()->get('bns.right_manager');

        $teacherIds = $gm->getUserIdsByRole('TEACHER', $this->getGroupId());
        $pupilIds = $gm->getUserIdsByRole('PUPIL', $this->getGroupId());
        $userId = $rm->getUserSessionId();

        return MediaFolderUserQuery::create()
            ->filterByTreeLevel(0)
            ->filterByTreeLeft(1)
            ->filterByUserId(array_merge($teacherIds, $pupilIds), \Criteria::IN)
            ->filterByUserId($userId, \Criteria::NOT_EQUAL)
            ->useUserRelatedByUserIdQuery()
                ->filterByArchived(false)
                ->orderByHighRoleId(\Criteria::ASC)
                ->orderByLastName()
            ->endUse()
            ->find();
    }

    public function getChildrenWithSpot()
    {
        if ($this->getIsChildrenPartial()) {
            return [];
        }

        $children = $this->getChildren(null, null, true, true);

        // optimize isEmpty calculation
        $folderIds = [];
        /** @var self $folder */
        foreach ($children as $folder) {
            if ($folder->getType() !== $this->getType() || $this->getIsLocker() || $folder->getIsUserFolder() || $folder->hasChildren()) {
                continue;
            } elseif ($this->getType() === $folder->getType()) {
                $folderIds[$folder->getId()] = $folder->getId();
            }
        }

        $mediaManager = new MediaFolderManager();
        $query = $mediaManager->getChildrenMediaQuery($this, $folderIds);
        $folderWithMediaIds = $query
            ->groupByMediaFolderId()
            ->select(['MediaFolderId'])
            ->find()->getArrayCopy()
        ;

        foreach ($children as $folder) {
            if (in_array((string)$folder->getId(), $folderWithMediaIds)) {
                $folder->isEmpty = false;
            } elseif (isset($folderIds[$folder->getId()])) {
                $folder->isEmpty = true;
            }
        }

        return $children;
    }

    public function getIsChildrenPartial()
    {
        return (!self::$hydrateChildrenFolder || (true !== self::$hydrateChildrenFolder && self::$hydrateChildrenFolder !== $this->getId())) ? : null;
    }

    /**
     * @return true when no subFolder and no active media
     */
    public function getIsEmpty()
    {
        if (null === $this->isEmpty) {
            if ($this->getIsLocker()) {
                $this->isEmpty = true;
            } elseif ($this->hasChildren() || $this->getIsUserFolder()) {
                $this->isEmpty = false;
            } elseif (isset($this->medias) || $this->getIsExternalFolder()) {
                $this->isEmpty = 0 === count($this->getMedias());
            } else {
                $mediaManager = new MediaFolderManager();
                $query = $mediaManager->getMediaQuery($this);
                $this->isEmpty = 0 === $query->count();
            }
        }

        return $this->isEmpty;
    }

    /**
     * Initialisation d'un répertoire d'utilisateurs
     * @param string $label
     * @throws \Exception
     */
    public function initUserFolder($label = "Espace utilisateurs")
    {
        if (null === $this->getGroupId() || $this->isNew()) {
            throw new \Exception('Cannot initUserFolder, root folder isNew or no groupId is set');
        }

        $hasUserFolder = MediaFolderGroupQuery::create()
            ->filterByGroupId($this->getGroupId())
            ->filterByIsUserFolder(true)
            ->count();

        if (!$hasUserFolder) {
            $userFolder = new MediaFolderGroup();
            $userFolder->setSlug('documents-utilisateurs-' . $this->getGroupId());
            $userFolder->setLabel("Dossiers des utilisateurs");
            $userFolder->setGroupId($this->getGroupId());
            $userFolder->insertAsFirstChildOf($this);
            $userFolder->setIsUserFolder(true);
            $userFolder->save();
        }
    }

    public function setMarker($marker)
    {
        $this->marker = $marker;
    }

    /**
     * Renvoie le marker utilisé dans l'API
     * @return string
     */
    public function getMarker()
    {
        return $this->marker ? : $this->getId() . '-' . $this->getType();
    }

    public function getUsageRatio()
    {
        if(!$this->isRoot() || !isset($this->showRatio))
        {
            return false;
        }
        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getUsageRatio();
    }

    public function getUsage()
    {
        if(!$this->isRoot() || !isset($this->showRatio))
        {
            return false;
        }
        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getUsage();
    }

    public function getNiceUsage()
    {
        if(!$this->isRoot() || !isset($this->showRatio))
        {
            return false;
        }
        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getUsage(true);
    }

    public function setUniqueKey($uniqueKey)
    {
        $this->uniqueKey = $uniqueKey;
    }

    public function getUniqueKey()
    {
        return $this->uniqueKey ?: strtolower($this->getType().$this->getId());
    }

    public function setRole($role)
    {
        $this->role = $role;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function getMediaLibraryRightManager()
    {
        return BNSAccess::getContainer()->get('bns.media_library_right.manager');
    }

    public function isReadable()
    {
        return $this->getMediaLibraryRightManager()->isReadable($this);
    }

    public function isManageable()
    {
        return $this->getMediaLibraryRightManager()->isManageable($this);
    }

    public function isWritable()
    {
        return $this->getMediaLibraryRightManager()->isWritable($this);
    }

    public function isPrivate()
    {
        return $this->getIsPrivate();
    }

    public function isActive()
    {
        return $this->getStatusDeletion() == MediaFolderManager::STATUS_ACTIVE;
    }

    public function isFavorite($userId = null)
    {
        if($userId == null)
        {
            $userId = BNSAccess::getContainer()->get('bns.right_manager')->getUserSessionId();
        }
        $manager =  BNSAccess::getContainer()->get('bns.media_folder.manager');
        $manager->setMediaFolderObject($this);
        return $manager->isFavorite($userId);
    }

    public function getParentKey()
    {
        if (!$this->isRoot()) {
            return $this->getParent()->getUniqueKey();
        }
        return null;
    }

    /**
     * Check si le dossier de groupe est un dossier système, i.e. une racine ou un dossier "spécial"
     *
     * @return bool
     */
    public function isSystem()
    {
        return $this->isRoot() || $this->getIsUserFolder() || $this->getIsExternalFolder() || !$this->getId();
    }

    public function createSlug()
    {
        if (!$this->isNew()) {
            $key = $this->getId();
        } else {
            $key = 'key-' . rand(999999999, min(9999999999, PHP_INT_MAX));
        }

        return 'mediafolder-group-' . $key;
    }
}
