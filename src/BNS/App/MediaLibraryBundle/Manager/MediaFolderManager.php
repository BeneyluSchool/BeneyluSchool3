<?php

namespace BNS\App\MediaLibraryBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderFavorites;
use BNS\App\MediaLibraryBundle\Model\MediaFolderFavoritesQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\ApP\CoreBundle\Access\BNSAccess;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MediaFolderManager
{
    /** @var  MediaFolderGroup $mediaFolder */
    protected $mediaFolder;
    protected $authorisedTypes = array('USER', 'GROUP');

    /** @var  BNSUserManager $userManager */
    protected $userManager;

    /** @var  BNSGroupManager $groupManager */
    protected $groupManager;

    /** @var  MediaManager $mediaManager */
    protected $mediaManager;

    const STATUS_ACTIVE = '1';                  // Dossier actif et visible
    const STATUS_GARBAGED = '0';                // Dossier mis en corbeille
    const STATUS_DELETED = '-1';                // Dossier supprimé définitivement
    const STATUS_GARBAGED_PARENT = '-2';        // Dossier dont le parent est à la corbeille

    const ERROR_MOVE_NOT_AUTHORISED = "ERROR_MOVE_NOT_AUTHORISED";

    public function __construct($userManager = null, $groupManager = null, $mediaManager = null)
    {
        //Cherche toujours une bonne solution pour injecter le conteneur depuis appel des classes propels
        if($userManager)
        {
            $this->userManager = $userManager;
        }else{
            $this->userManager = BNSAccess::getContainer()->get('bns.user_manager');
        }
        if($groupManager)
        {
            $this->groupManager = $groupManager;
        }else{
            $this->groupManager = BNSAccess::getContainer()->get('bns.group_manager');
        }
        if($mediaManager)
        {
            $this->mediaManager = $mediaManager;
        }else{
            $this->mediaManager = BNSAccess::getContainer()->get('bns.media.manager');
        }

    }

    /**
     * Set en object de la classe le mediaFolder en paramètre
     * @param $mediaFolder
     */
    public function setMediaFolderObject($mediaFolder)
    {
        $this->mediaFolder = $mediaFolder;
    }

    /**
     * Renvoie le media folder de la classe
     * @return MediaFolderGroup
     */
    public function getMediaFolderObject()
    {
        return $this->mediaFolder;
    }

    public function create($label, $parentId, $parentType)
    {
        $parent = $this->find($parentId, $parentType);


        switch($parent->getType())
        {
            case 'USER':
                $child = new MediaFolderUser();
                $child->setIsPrivate(true);
                break;
            case 'GROUP':
                $child = new MediaFolderGroup();
                $child->setIsPrivate(false);
                break;
        }
        $child->insertAsFirstChildOf($parent);
        $child->setLabel($label);
        $child->save();

        $this->setMediaFolderObject($parent);
        $this->alphaReoganize();

        return $child;
    }

    /**
     * @param $id Id du dossier
     * @param $type type du dossier (USER ou GROUP)
     * @return MediaFolderGroup|\BNS\App\MediaLibraryBundle\Model\MediaFolderUser|bool
     */
    public function find($id, $type)
    {
        if(!in_array($type, $this->authorisedTypes) || $id == null || !$id)
        {
            return false;
        }
        switch($type)
        {
            case 'USER':
                $query = MediaFolderUserQuery::create();
                break;
            case 'GROUP':
                $query = MediaFolderGroupQuery::create();
                break;
        }
        $mediaFolder = $query->findOneById($id);
        $this->setMediaFolderObject($mediaFolder);
        return $mediaFolder;
    }

    /////    OPERATIONS sur les objets medias folders    \\\\\\

    public function togglePrivate($value = null)
    {
        if($value === null)
        {
            $value = !$this->mediaFolder->getIsPrivate();
        }

        $base = $this->mediaFolder;

        // Cascade sur les fichiers
        foreach ($this->getMedias() as $media) {
            $this->mediaManager->setMediaObject($media);
            $this->mediaManager->togglePrivate($value);
        }

        // Cascade sur les sous-dossiers
        foreach ($this->mediaFolder->getDescendants() as $subfolder) {
            $this->setMediaFolderObject($subfolder);
            $this->togglePrivate($value);
        }

        $this->setMediaFolderObject($base);
        $this->mediaFolder->setIsPrivate($value);
        $this->mediaFolder->save();
    }

    public function toggleLocker()
    {
        $this->mediaFolder->setIsLocker(!$this->mediaFolder->getIsLocker());
        $this->mediaFolder->save();
    }

    /**
     * Fonction de restauration : en deux étapes, toujours en soft delete
     */
    public function restore()
    {
        $base = $this->mediaFolder;

        // Restaure les médias qui avaient été cascade-supprimés
        foreach ($this->getMedias(MediaManager::STATUS_GARBAGED_PARENT) as $media) {
            $this->mediaManager->setMediaObject($media);
            $this->mediaManager->restore();
        }

        // Restaure les sous-dossiers qui avaient été cascade-supprimés
        foreach ($this->mediaFolder->getDescendants() as $subfolder) {
            /** @var MediaFolderUser $subfolder */
            if (self::STATUS_GARBAGED_PARENT == $subfolder->getStatusDeletion()) {
                $this->setMediaFolderObject($subfolder);
                $this->restore();
            }
        }

        $this->setMediaFolderObject($base);
        $this->getMediaFolderObject()->reload();

        $this->mediaFolder->setStatusDeletion(self::STATUS_ACTIVE);
        $this->mediaFolder->setDeletedBy(null);
        $this->mediaFolder->save();
    }

    /**
     * Fonction de suppression : en deux étapes, toujours en soft delete
     */
    public function delete($userId, $status = null, $fromParent = false)
    {
        if($status == null)
        {
            $status = $this->mediaFolder->getStatusDeletion();
        }

        $logger = BNSAccess::getContainer()->get('logger');

        $base = $this->mediaFolder;
        switch($status)
        {
            case self::STATUS_ACTIVE:
                // Marque les médias comme étant supprimés par leur parent
                foreach ($this->getMedias(array(MediaManager::STATUS_ACTIVE, MediaManager::STATUS_GARBAGED)) as $media) {
                    $this->mediaManager->setMediaObject($media);
                    $this->mediaManager->delete($userId, null, true);
                }

                // Cascade sur les sous-dossiers
                foreach ($this->mediaFolder->getDescendants() as $subfolder) {
                    $this->setMediaFolderObject($subfolder);
                    $this->delete($userId, null, true);
                }
                $this->setMediaFolderObject($base);
                $this->getMediaFolderObject()->reload();

                if ($fromParent) {
                    $this->mediaFolder->setStatusDeletion(self::STATUS_GARBAGED_PARENT);
                } else {
                    $this->mediaFolder->setStatusDeletion(self::STATUS_GARBAGED);
                }
                $this->mediaFolder->setDeletedBy($userId);
                $this->mediaFolder->save();
                //Pas d'autre traitement, il n'y a pas de libération d'espace
                break;
            case self::STATUS_GARBAGED:
            case self::STATUS_GARBAGED_PARENT:
                // dossier était déjà supprimé, son parent devient supprimé => simple mise à jour du statut
                if (self::STATUS_GARBAGED == $status && $fromParent) {
                    $this->mediaFolder->setStatusDeletion(self::STATUS_GARBAGED_PARENT);
                    $this->mediaFolder->setDeletedBy($userId);
                    $this->mediaFolder->save();
                    break;
                }

                // supprime les médias
                foreach($this->getMedias(array(MediaManager::STATUS_GARBAGED, MediaManager::STATUS_GARBAGED_PARENT)) as $media)
                {
                    $this->mediaManager->setMediaObject($media);
                    $this->mediaManager->delete($userId, MediaManager::STATUS_GARBAGED);
                }

                foreach($this->mediaFolder->getDescendants() as $descendant)
                {
                    $this->setMediaFolderObject($descendant);
                    $this->delete($userId, self::STATUS_GARBAGED);
                }

                $this->setMediaFolderObject($base);
                $this->getMediaFolderObject()->reload();

                $this->mediaFolder->setStatusDeletion(self::STATUS_DELETED);
                $this->mediaFolder->setDeletedBy($userId);
                $this->mediaFolder->save();
                break;

        }
    }

    /**
     * Renvoie les dossiers à la corbeille pour un utilisateur
     */
    public function getGarbagedMediaFolders($userId)
    {
        $group = MediaFolderGroupQuery::create()
            ->filterByStatusDeletion(self::STATUS_GARBAGED)
            ->filterByDeletedBy($userId)
            ->find();
        $user = MediaFolderUserQuery::create()
            ->filterByStatusDeletion(self::STATUS_GARBAGED)
            ->filterByDeletedBy($userId)
            ->find();
        $return = array();
        foreach($group as $item)
        {
            $return[] = $item;
        }
        foreach($user as $item)
        {
            $return[] = $item;
        }
        return $return;
    }

    public function toggleFavorite($userId, $value = null)
    {
        $fav = MediaFolderFavoritesQuery::create()
            ->filterByUserId($userId)
            ->filterByMediaFolderType($this->getMediaFolderObject()->getType())
            ->filterByMediaFolderId($this->getMediaFolderObject()->getId())
            ->findOneOrCreate();

        if($fav->isNew() || $value == true)
        {
            $fav->save();
        }else{
            $fav->delete();
        }
    }

    public function getFavoritesMediaFolders($userId)
    {
        $folderFavs = MediaFolderFavoritesQuery::create()
            ->filterByUserId($userId)
            ->find();
        $users = array();
        $groups = array();
        /** @var MediaFolderFavorites $folderFav */
        foreach($folderFavs as $folderFav)
        {
            if($folderFav->getMediaFolderType() == 'USER')
            {
                $users[] = $folderFav->getMediaFolderId();
            }elseif($folderFav->getMediaFolderType() == 'GROUP')
            {
                $groups[] = $folderFav->getMediaFolderId();
            }
        }
        $groupFavs = MediaFolderGroupQuery::create()
            ->findById($groups);
        $userFavs = MediaFolderUserQuery::create()
            ->findById($users);
        $return = array();
        foreach($groupFavs as $item)
        {
            $return[] = $item;
        }
        foreach($userFavs as $item)
        {
            $return[] = $item;
        }
        return $return;
    }

    public function isFavorite($userId)
    {
        if(!isset($this->favoritesCache[$userId]))
        {
            $cache = array('USER' => array(),'GROUP' => array());
            $favorites = MediaFolderFavoritesQuery::create()->filterByUserId($userId)->find()->toArray();

            foreach($favorites as $favorite)
            {
                $cache[$favorite['MediaFolderType']][] = $favorite['MediaFolderId'];
            }

            $this->favoritesCache[$userId] = $cache;

        }
        return in_array($this->getMediaFolderObject()->getId(),$this->favoritesCache[$userId][$this->getMediaFolderObject()->getType()]);
    }

    /**
     * Déplace dans le media folder destination
     */
    public function move($mediaFolderDestination)
    {
        /**
         * TODO pour multi arborescence (HORS SCOPE ACTUEL)
         *
         * Si changement de type faire ici
         *  - Destruction du courant
         *  - Création du nouveau
         *  - Suivi des documents
         *
         * Puis insertion dans parent
         */
        $mediaFolder = $this->getMediaFolderObject();

        if($mediaFolder->getType() != $mediaFolderDestination->getType())
        {
            throw new BadRequestHttpException(self::ERROR_MOVE_NOT_AUTHORISED);
        }

        if($mediaFolder->getScopeValue() != $mediaFolderDestination->getScopeValue())
        {
            throw new BadRequestHttpException(self::ERROR_MOVE_NOT_AUTHORISED);
        }

        $ancestorIds = array();

        foreach($mediaFolderDestination->getAncestors() as $a)
        {
            $ancestorIds[] = $a->getId();
        }

        //On vérifie que l'on essaie pas d'envoyer dans un de ses fils
        if(in_array($mediaFolder->getId(),$ancestorIds))
        {
            throw new BadRequestHttpException(self::ERROR_MOVE_NOT_AUTHORISED);
        }

        $mediaFolder->moveToLastChildOf($mediaFolderDestination);
        $mediaFolder->save();

        $this->setMediaFolderObject($mediaFolderDestination);
        $this->alphaReoganize();
    }

    /**
     * Réoganise alphabétiquement les enfants du groupe courant
     */
    public function alphaReoganize()
    {
        $ordered = array();
        foreach($this->mediaFolder->getChildren(null, null, true, false) as $child)
        {
            $ordered[$child->getLabel()] = $child;
        }

        ksort($ordered);

        foreach($ordered as $child)
        {
            $this->mediaFolder->reload();
            $child->reload();
            $child->moveToLastChildOf($this->mediaFolder);
            $child->save();
        }
    }

    public function getUserFolder(User $user)
    {
        $mediaFolder = MediaFolderUserQuery::create()->findRoot($user->getId());
        $this->setMediaFolderObject($mediaFolder);
        return $mediaFolder;
    }

    public function getGroupFolder(Group $group)
    {
        $mediaFolder = MediaFolderGroupQuery::create()->findRoot($group->getId());
        $this->setMediaFolderObject($mediaFolder);
        return $mediaFolder;
    }

    /**
     * Gets the virtual Spot folder for the given MediaFolderGroup (only if root folder)
     *
     * @param MediaFolderGroup $folder
     * @param bool $hydrateResources whether to also fetch external resources
     * @param bool $getOnlyWithResources whether to get the folder only if it has some resources
     * @return MediaFolderGroup|null
     *
     * @deprecated Use actual external folder now
     */
    public function getSpotFolder(MediaFolderGroup $folder, $hydrateResources = false, $getOnlyWithResources = false)
    {
        $container = BNSAccess::getContainer();

        if ($folder->isRoot() && $container && $container->hasParameter('paas_use') && $container->getParameter('paas_use')) {
            $spotFolder = new MediaFolderGroup();
            $spotFolder->setSlug('ressources-spot-' . $folder->getGroupId());
            $spotFolder->setLabel($container->get('translator')->trans('LABEL_SPOT_FOLDER', [], 'MEDIA_LIBRARY'));
            $spotFolder->setGroup($folder->getGroup());
            $spotFolder->setParent($folder);
            $spotFolder->setMarker($folder->getGroupId().'-SPOT');
            $spotFolder->setUniqueKey('spot'.$folder->getGroupId());
            $spotFolder->setRole('external');

            if ($hydrateResources) {
                $resources = $container->get('bns.paas_manager')
                    ->getMediaLibraryResources(
                        $container->get('bns.right_manager')->getUserSession(),
                        $folder->getGroup()
                    )
                ;
                if ($getOnlyWithResources && !count($resources)) {
                    return null;
                }

                $spotFolder->setMedias($resources);
            }

            return $spotFolder;
        } else {
            return null;
        }
    }

    /**
     * Renvoie les medias associés à un media folder, pour les deux type de
     *
     * @param string $status Un status sur lequel filter les médias. Médias actifs par défaut.
     *
     * @return mixed
     */
    public function getMedias($status = MediaManager::STATUS_ACTIVE)
    {

        $container = BNSAccess::getContainer();

        if($container->get('bns.media.manager')->getNoMediaChildren() == true)
        {
            return new \PropelCollection();
        }

        $mediaFolder = $this->getMediaFolderObject();

        if (!$container->get('bns.media_library_right.manager')->canReadFolderContent($mediaFolder)) {
            return new \PropelCollection();
        }

        $withPrivate = $container->get('bns.media_library_right.manager')->canManageFolder($mediaFolder);

        $query = MediaQuery::create()
            ->filterByExpiresAt(null, \Criteria::ISNULL)
            ->_or()
            ->filterByExpiresAt(time(), \Criteria::GREATER_EQUAL)
            ->_if($status)
                ->filterByStatusDeletion($status)
            ->_endif()
            ->filterByMediaFolderType($mediaFolder->getType())
            ->filterByMediaFolderId($mediaFolder->getId());

        if(!$withPrivate)
        {
            $query->filterByIsPrivate(false);
        }

        return $query->find();
    }

    /**
     * Renvoi le ratio en % d'utilisation de l'espace disque
     * @return bool|float
     */
    public function getUsageRatio()
    {
        $mediaFolder = $this->getMediaFolderObject();
        if(!$mediaFolder->isRoot())
        {
            return false;
        }
        switch($mediaFolder->getType())
        {
            case 'USER':
                $this->userManager->setUserById($mediaFolder->getOwnerId());
                return $this->userManager->getResourceUsageRatio();
            case 'GROUP':
                $this->groupManager->setGroupById($mediaFolder->getOwnerId());
                return $this->groupManager->getResourceUsageRatio();
        }
    }

    /**
     * Renvoi le ratio en % d'utilisation de l'espace disque
     * @param bool $asKeys whether to format result as an indexed array
     * @return bool|float
     */
    public function getUsage($asKeys = false)
    {
        $mediaFolder = $this->getMediaFolderObject();
        if(!$mediaFolder->isRoot())
        {
            return false;
        }
        $current = 0;
        $total = 0;
        switch($mediaFolder->getType())
        {
            case 'USER':
                $this->userManager->setUserById($mediaFolder->getOwnerId());
                $current = $this->userManager->getUser()->getResourceUsedSize();
                $total = $this->userManager->getRessourceAllowedSize();
                break;
            case 'GROUP':
                $this->groupManager->setGroupById($mediaFolder->getOwnerId());
                $current = $this->groupManager->getResourceUsedSize();
                $total = $this->groupManager->getResourceAllowedSize();
                break;
        }

        if ($asKeys) {
            return [
                'current' => $current,
                'total' => $total,
            ];
        } else {
            return [$current, $total];
        }
    }

    public function getSize()
    {
        $folder = $this->getMediaFolderObject();
        $rm = BNSAccess::getContainer()->get('bns.media_library_right.manager');
        $size = 0;

        foreach($folder->getMedias() as $media)
        {
            if($rm->canReadMedia($media))
            {
                $size += $media->getSize(false);
            }
        }
        foreach($folder->getDescendants() as $dFolder)
        {
            if($rm->canReadFolder($dFolder))
            {
                foreach($folder->getMedias() as $media)
                {
                    if($rm->canReadMedia($media))
                    {
                        $size += $media->getSize(false);
                    }
                }
            }
        }
        return $size;
    }

    /**
     * Gets the size of the given folder, representing the size of all of its non-deleted medias, including those of
     * subfolders.
     * Folder-type agnostic and does not rely on user permissions to retrieve medias.
     *
     * @param MediaFolderGroup|MediaFolderUser $folder
     * @return int
     */
    public function getSizeSimple($folder)
    {
        $size = 0;
        if ($folder->getStatusDeletion() !== self::STATUS_DELETED) {
            /** @var Media[] $medias */
            $medias = MediaQuery::create()
                ->filterByStatusDeletion(MediaManager::STATUS_DELETED, \Criteria::NOT_EQUAL)
                ->filterByMediaFolderType($folder->getType())
                ->filterByMediaFolderId($folder->getId())
                ->find()
            ;
            foreach ($medias as $media) {
                $size += $media->getSize(false);
            }

            /** @var MediaFolderGroup[]|MediaFolderUser[] $subfolders */
            $subfolders = $folder->getDescendants();
            foreach ($subfolders as $subfolder) {
                if ($subfolder->getStatusDeletion() === MediaFolderManager::STATUS_DELETED || $subfolder->isSystem()) {
                    continue;
                }
                /** @var Media[] $medias */
                $medias = MediaQuery::create()
                    ->filterByStatusDeletion(MediaManager::STATUS_DELETED, \Criteria::NOT_EQUAL)
                    ->filterByMediaFolderType($subfolder->getType())
                    ->filterByMediaFolderId($subfolder->getId())
                    ->find()
                ;
                foreach ($medias as $media) {
                    $size += $media->getSize(false);
                }
            }
        }

        return $size;
    }

    public function mCopy($mediaFolder)
    {
        $parent = $this->getMediaFolderObject();
        $container = BNSAccess::getContainer();
        /** @var MediaFolderGroup $mediaFolder */
        $mc = $container->get('bns.media.creator');
        $rm = $container->get('bns.media_library_right.manager');
        //Vérification que l'on a assez d'espace en destination
        $size = $this->getSize();
        $mc->checkSize($mediaFolder, $size , $this->userManager->getUser()->getId());
        //Ok on commence la copie
        $this->recCopy($parent, $mediaFolder,$mc,$rm);
        $mc->addSize($mediaFolder ,$size);
    }
    public function mCopyFromArchivedUser($folderFromUser, $folderDestUser)
    {
        $container = BNSAccess::getContainer();
        /** @var MediaFolderGroup $folderDestUser */
        $mc = $container->get('bns.media.creator');
        $rm = $container->get('bns.media_library_right.manager');
        //Vérification que l'on a assez d'espace en destination
        $size = $this->getSize();
        $mc->checkSize($folderDestUser, $size , $this->userManager->getUser()->getId());
        //Ok on commence la copie
        $this->recCopy($folderFromUser, $folderDestUser,$mc,$rm);
        $mc->addSize($folderDestUser ,$size);
    }

    public function recCopy($source, $destination, MediaCreator $mediaCreator, MediaLibraryRightManager $mediaRightManager)
    {
        $mm = BNSAccess::getContainer()->get('bns.media.manager');
        /** @var MediaFolderGroup $source */
        /** @var MediaFolderGroup $destination */

        switch($destination->getType())
        {
            case 'GROUP':
                $folder = new MediaFolderGroup();
                break;
            case 'USER':
                $folder = new MediaFolderUser();
                break;
        }
        $folder->setLabel($source->getLabel());
        $folder->setIsPrivate($source->isPrivate());
        $folder->insertAsFirstChildOf($destination);
        $folder->save();
        //Garde fou pour ne pas dérouler dans des dossiers qui viennent d'être créé et partir en boucle infinie
        $this->saved[] = $folder->getId();

        foreach($source->getChildren(null, null, false) as $child)
        {
            if($mediaRightManager->canReadFolder($child) && !in_array($child->getId(),$this->saved))
            {
                $this->recCopy($child, $folder, $mediaCreator, $mediaRightManager);
            }
        }

        foreach($source->getMedias() as $media)
        {
            if($mediaRightManager->canReadMedia($media))
            {
                $bCopy = $media;
                $copied = $media->copy();
                $copied->setMediaFolderId($folder->getId());
                $copied->setMediaFolderType($folder->getType());
                $copied->setSize($media->getSize(false));
                $copied->save();

                if($mm->getFileSystem()->has($bCopy->getFilePath()))
                {
                    $mm->getFileSystem()->write($copied->getFilePath(),$mm->getFileSystem()->read($bCopy->getFilePath()));
                }
            }
        }
    }

}
