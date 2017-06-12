<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Model\om\BaseMediaFolderGroup;

class MediaFolderGroup extends BaseMediaFolderGroup
{
    use TranslatorTrait;

    const LABEL_GENERAL_FOLDER = 'LABEL_GENERAL_FOLDER';

    protected $medias;

    protected $marker;

    protected $uniqueKey;

    protected $role;

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

    public function getGroupType()
    {
        if($this->getLevel() == 0)
        {
            return $this->getGroup()->getType();
        }
    }

    public function getChildren($criteria = null, \PropelPDO $con = null, $withUserFolders = true, $withSpotFolder = false)
    {
        $groupType = $this->getGroup()->getGroupType()->getType();
        if($groupType != 'CLASSROOM')
        {
            $withUserFolders = false;
        }



        if(!$this->getIsUserFolder() || $groupType != 'CLASSROOM')
        {
            $container = BNSAccess::getContainer();
            $criteria = new \Criteria();
            $criteria->add(MediaFolderGroupPeer::STATUS_DELETION,MediaFolderManager::STATUS_ACTIVE);
            if (!$withUserFolders) {
                $criteria->add(MediaFolderGroupPeer::IS_USER_FOLDER,false);
            }
            $withPrivate = $container->get('bns.media_library_right.manager')->canManageFolder($this);
            if(!$withPrivate)
            {
               $criteria->add(MediaFolderGroupPeer::IS_PRIVATE,false);
            }

            // can't see content => add an impossible condition, to have an empty collection
            if (!$container->get('bns.media_library_right.manager')->canReadFolderContent($this)) {
                $criteria->add(MediaFolderUserPeer::ID, 0);
            }

            if ($withSpotFolder) {
                $this->getGroup()->getExternalFolder(); // refresh spot folder
            }

            $children = parent::getChildren($criteria, $con);

            return $children;
        }

        // TODO : Remove BnsAccess Call
        //Recupération des dossiers users
        $gm = BNSAccess::getContainer()->get('bns.group_manager');
        $rm = BNSAccess::getContainer()->get('bns.right_manager');
        $gm->setGroupById($this->getGroupId());

        $teacherIds = $gm->getUsersByRoleUniqueNameIds('TEACHER');
        $pupilIds = $gm->getUsersByRoleUniqueNameIds('PUPIL');

        $teacherFolders = MediaFolderUserQuery::create()
            ->select('media_folder_user.id')
            ->filterByUserId($teacherIds)
            ->filterByTreeLevel(0)
            //Enleve l'utilisateur courant
            ->filterByUserId($rm->getUserSessionId(),\Criteria::NOT_EQUAL)
            ->find()->toArray();
        $pupilFolders = MediaFolderUserQuery::create()
            ->select('media_folder_user.id')
            ->filterByUserId($pupilIds)
            ->filterByTreeLevel(0)
            ->filterByUserId($rm->getUserSessionId(),\Criteria::NOT_EQUAL)
            ->find()->toArray();
        $coll = MediaFolderUserQuery::create()->filterById(array_merge($teacherFolders,$pupilFolders))
            ->useUserRelatedByUserIdQuery()
            ->orderByHighRoleId(\Criteria::ASC)
            ->orderByLastName()
            ->endUse()
            ->find();
        foreach($coll as $col)
        {
            $col->realParent = $this;
        }
        return $coll;
    }

    public function getChildrenWithSpot()
    {
        return $this->getChildren(null, null, true, true);
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
        if(!$this->isRoot())
        {
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

}
