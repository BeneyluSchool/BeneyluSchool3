<?php

namespace BNS\App\MediaLibraryBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\MediaLibraryBundle\Manager\MediaFolderManager;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\om\BaseMediaFolderUser;

class MediaFolderUser extends BaseMediaFolderUser
{

    public function getChildren($criteria = null, \PropelPDO $con = null)
    {
        $container = BNSAccess::getContainer();
        $withPrivate = $container->get('bns.media_library_right.manager')->canManageFolder($this);

        $criteria = new \Criteria();
        $criteria->add(MediaFolderUserPeer::STATUS_DELETION,MediaFolderManager::STATUS_ACTIVE);
        if(!$withPrivate)
        {
            $criteria->add(MediaFolderUserPeer::IS_PRIVATE,false);
        }

        // can't see content => add an impossible condition, to have an empty collection
        if (!$container->get('bns.media_library_right.manager')->canReadFolderContent($this)) {
            $criteria->add(MediaFolderUserPeer::ID, 0);
        }

        $res = parent::getChildren($criteria, $con);
        foreach($res as $r)
        {
            $r->realParent = $this;
        }
        return $res;
    }

    /**
     * Renvoie le type de dossier pour les traitements globaux sur les médias folder
     * @return string USER forcément
     */
    public function getType()
    {
        return 'USER';
    }

    public function getOwnerId()
    {
        return $this->getUserId();
    }

    /**
     * Renvoie les medias actifs associés au dossier
     * @return array|mixed|\PropelObjectCollection
     */
    public function getMedias()
    {
        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getMedias();
    }

    /**
     * Renvoie le marker utilisé dans l'API
     * @return string
     */
    public function getMarker()
    {
        return $this->getId() . '-' . $this->getType();
    }

    public function getUser()
    {
        return $this->getUserRelatedByUserId();
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

    public function getNiceUsage ()
    {
        if(!$this->isRoot() || !isset($this->showRatio))
        {
            return false;
        }
        $mediaManager = new MediaFolderManager();
        $mediaManager->setMediaFolderObject($this);
        return $mediaManager->getUsage(true);
    }

    public function getUniqueKey()
    {
        return strtolower($this->getType().$this->getId());
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
     * Check si le dossier d'utilisateur est un dossier système, i.e. une racine.
     *
     * @return bool
     */
    public function isSystem()
    {
        return $this->isRoot() || $this->getIsShareDestination();
    }

}
