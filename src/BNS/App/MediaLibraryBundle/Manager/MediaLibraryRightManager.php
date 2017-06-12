<?php

namespace BNS\App\MediaLibraryBundle\Manager;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use \BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaJoinObjectQuery;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


class MediaLibraryRightManager
{
    /**
     * @var BNSRightManager $rightManager
     */
    protected $rightManager;

    /**
     * @var BNSUserManager $userManager
     */
    protected $userManager;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    /**
     * @var User $user
     */
    protected $user;

    /**
     * @var array $rightCache
     */
    protected $cache;

    protected $canReadFolderContentCache = array();

    public function __construct(BNSRightManager $rightManager)
    {
        $this->rightManager = $rightManager;
        $this->userManager = $rightManager->getUserManager();
        $this->user = $this->userManager->getUser();
        $this->groupManager = $this->rightManager->getGroupManager();
        $this->cache = array();
    }

    /**
     * Renvoi l'utilisateur en cours
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Renvoi le groupManager
     * @return BNSGroupManager
     */
    public function getGroupManager()
    {
        return $this->groupManager;
    }

    /**
     * Renvoie le right Manager
     * @return BNSRightManager
     */
    public function getRightManager()
    {
        return $this->rightManager;
    }

    ////////////////   FONCTION LIEES AUX DOSSIERS   \\\\\\\\\\\\\\\\\\\\\\\

    public function canManageFolder($mediaFolder)
    {
        if(!isset($this->canManageFolder[$mediaFolder->getMarker()]))
        {
            if ($mediaFolder->getType() == 'USER') {
                $this->canManageFolder[$mediaFolder->getMarker()] =  $this->canManageUser($mediaFolder->getUserId());
            }
            elseif ($mediaFolder->getType() == 'GROUP') {
                $this->canManageFolder[$mediaFolder->getMarker()] = $this->canManageGroup($mediaFolder->getGroupId());
            }
            if(!isset($this->canManageFolder[$mediaFolder->getMarker()]))
            {
                $this->canManageFolder[$mediaFolder->getMarker()] = false;
            }
        }
        return $this->canManageFolder[$mediaFolder->getMarker()];
    }

    public function canReadFolder($mediaFolder)
    {
        if(!isset($this->canReadFolder[$mediaFolder->getMarker()]))
        {
            if(isset($this->canManageFolder[$mediaFolder->getMarker()]))
            {
                if($this->canManageFolder[$mediaFolder->getMarker()] == true)
                {
                    $this->canReadFolder[$mediaFolder->getMarker()] = true;
                    return true;
                }
            }
            if ($mediaFolder->getType() == 'USER') {
                if(!$mediaFolder->isPrivate())
                {
                    $this->canReadFolder[$mediaFolder->getMarker()] = $this->canReadUser($mediaFolder->getUserId());
                }else{
                    $this->canReadFolder[$mediaFolder->getMarker()] = $this->canManageUser($mediaFolder->getUserId());
                }
            }elseif ($mediaFolder->getType() == 'GROUP') {
                if(!$mediaFolder->isPrivate())
                {
                    $this->canReadFolder[$mediaFolder->getMarker()] = $this->canReadGroup($mediaFolder->getGroupId());
                }else{
                    $this->canReadFolder[$mediaFolder->getMarker()] = $this->canManageGroup($mediaFolder->getGroupId());
                }

            }
            if(!isset($this->canReadFolder[$mediaFolder->getMarker()]))
            {
                $this->canReadFolder[$mediaFolder->getMarker()] = false;
            }
        }
        return $this->canReadFolder[$mediaFolder->getMarker()];
    }

    /**
     * @param MediaFolderUser|MediaFolderGroup $mediaFolder
     */
    public function canReadFolderContent($mediaFolder)
    {
        $key = $mediaFolder->getMarker();

        if (!isset($this->canReadFolderContentCache[$key])) {
            if ($this->canReadFolder($mediaFolder)) {
                // can read a folder => assume that can see its content
                $result = true;

                if ('GROUP' === $mediaFolder->getType()) {
                    if ($mediaFolder->getIsLocker()) {
                        // can see content only if can manage the locker
                        $result = $this->canManageFolder($mediaFolder);
                    }
                }
            } else {
                // can't read a folder => can't see its content
                $result = false;
            }

            $this->canReadFolderContentCache[$key] = $result;
        }

        return $this->canReadFolderContentCache[$key];
    }

    /**
     * L'utilisateur set dans le resourceManager peut il créer dans $parent un label
     * @param $parent (ResourceLabelUser OU ResourceLabelGroup)
     * @return boolean
     */
    public function canCreateFolder($mediaFolder)
    {
        return $this->canManageFolder($mediaFolder);
    }

    /**
     * L'utilisateur set dans le resourceManager peut il éditer $label
     * @param $label (ResourceLabelUser OU ResourceLabelGroup)
     * @return boolean
     */
    public function canUpdateFolder($mediaFolder)
    {
        return $this->canManageFolder($mediaFolder);
    }

    /**
     * L'utilisateur set dans le resourceManager peut il supprimer $label
     * @param $label (ResourceLabelUser OU ResourceLabelGroup)
     * @return boolean
     */
    public function canDeleteFolder($mediaFolder)
    {
        return $this->canManageFolder($mediaFolder);
    }

    ////////////////   FONCTION LIEES AUX MEDIAS   \\\\\\\\\\\\\\\\\\\\\\\

    /**
     * Est il l'auteur de la ressource
     * @param type Media $media
     * @return boolean
     */
    public function isAuthor(Media $media)
    {
        return $media->getUserId() == $this->getUser()->getId();
    }

    /**
     * L'utilisateur peut il créer une resource dans ce label ?
     * @param $label
     * @return boolean
     */
    public function canCreateMedia($mediaFolder)
    {
        return $mediaFolder == null ? false : $this->canManageFolder($mediaFolder);
    }

    /**
     * L'utilisateur peut il lire la ressource ?
     * @param Media $media
     * @param boolean $light Pour baisse rle nibveau d'exigence et uniquement se baser sur le fait qu'il il y a un groupe en commun
     * @return boolean
     */
    public function canReadMedia(Media $media, $light = false)
    {
        if(!isset($this->canReadMedia[$media->getId()]))
        {
            if(isset($this->canManageMedia[$media->getId()]))
            {
                if($this->canManageMedia[$media->getId()] == true)
                {
                    $this->canReadMedia[$media->getId()] = true;
                    return true;
                }
            }
            $currentUser = $this->getUser();

            if($light == true)
            {
                //Cas "Simple" uniquement pour l'affichage dans le parsing
                if ($media->getMediaFolderType() == 'GROUP') {
                    $userGroupIds = $this->rightManager->getUserManager()->getGroupsIdsUserBelong();
                    if ($mediaFolder = $media->getMediaFolder()) {
                        $folderGroupId = $mediaFolder->getGroupId();
                        if (in_array($folderGroupId, $userGroupIds)) {
                            $this->canReadMedia[$media->getId()] = true;
                        } else {
                            // try to fallback if user group is a child of media group
                            $groupType = $this->groupManager->getGroupeType($folderGroupId);
                            if ($groupType && 'CLASSROOM' === $groupType->getType()) {
                                $parent = $this->groupManager->setGroupById($folderGroupId)->getParent();
                                if ('SCHOOL' === $parent->getType() && in_array($parent->getId(), $userGroupIds)) {
                                    $this->canReadMedia[$media->getId()] = true;
                                }
                            }
                        }
                    }
                } elseif($media->getMediaFolderType() == 'USER'){
                    if ($mediaFolder = $media->getMediaFolder()) {
                        $this->userManager->setUser($this->rightManager->getUserSession());
                        $myGroupIds = $this->userManager->getGroupsIdsUserBelong();
                        $this->userManager->setUserById($mediaFolder->getUserId());
                        $mediaGroupIds = $this->userManager->getGroupsIdsUserBelong();
                        // FIXME security
                        foreach ($myGroupIds as $id) {
                            if (in_array($id, $mediaGroupIds)) {
                                return true;
                            }
                        }
                    }
                }
            } else {
                // Cas le plus simple
                if ($this->isAuthor($media)) {
                    $this->canReadMedia[$media->getId()] = true;
                } else {
                    $mediaFolder = $media->getMediaFolder();
                    if($media->getMediaFolderType() == 'GROUP' && $mediaFolder && in_array($mediaFolder->getGroupId(), $this->userManager->getGroupIdsWherePermission('MEDIA_LIBRARY_ACCESS')))
                    {
                        if(!$media->isPrivate())
                        {
                            $this->canReadMedia[$media->getId()] = true;
                        }else{
                            $this->userManager->setUser($this->rightManager->getUserSession());
                            if(in_array(
                                $mediaFolder->getGroupId(),
                                array_merge(
                                    $this->userManager->getGroupIdsWherePermission('MEDIA_LIBRARY_USERS_ADMINISTRATION'),
                                    $this->userManager->getGroupIdsWherePermission('MEDIA_LIBRARY_ADMINISTRATION')
                                )
                            ))
                            {
                                $this->canReadMedia[$media->getId()] = true;
                            }
                        }
                    } elseif ($media->getMediaFolderType() == 'USER') {
                        $mediaFolder = $media->getMediaFolder();
                        if ($mediaFolder) {
                            if ($mediaFolder->getUserId() == $currentUser->getId()) {
                                $this->canReadMedia[$media->getId()] = true;
                            } elseif (!$media->isPrivate()) {
                                $this->canReadMedia[$media->getId()] = $this->canReadUser($mediaFolder->getUserId());
                            } else {
                                $this->userManager->setUserById($mediaFolder->getUserId());
                                if ($this->userManager->isAdult()) {
                                    $this->canReadMedia[$media->getId()] = false;
                                } else {
                                    $this->canReadMedia[$media->getId()] = $this->canManageUser($mediaFolder->getUserId());
                                }
                            }
                        }
                    }
                }
            }

            if(!isset($this->canReadMedia[$media->getId()]))
            {
                $this->canReadMedia[$media->getId()] = false;
            }
        }
        return $this->canReadMedia[$media->getId()];
    }


    /**
     * @param Media $media
     * @param string $objectType object classname ou FQN
     * @param int $objectId object id
     * @return bool
     */
    public function canReadMediaJoined(Media $media, $objectType, $objectId)
    {
        if ($this->canReadMedia($media)) {
            return true;
        }
        // find joined media
        $count = MediaJoinObjectQuery::create()
            ->filterByMediaId($media->getId())
            ->filterByObjectClass($objectType)
            ->filterByObjectId($objectId)
            ->count()
        ;
        if (0 === $count) {
            return false;
        }

        return $this->rightManager->canReadObject($objectType, $objectId);
    }

    /**
     * L'utilisateur peut il manager la ressource ?
     *
     * @param Media $media
     * @param null|MediaFolderGroup $mediaFolder	Si null, c'est un label User donc inutile de le lier
     *
     * @return boolean
     */
    public function canManageMedia(Media $media, $mediaFolder = null)
    {
        if(!isset($this->canManageMedia[$media->getId()]))
        {
            $currentUser = $this->rightManager->getUserSession();
            $this->userManager->setUser($currentUser);
            $mediaFolder = $media->getMediaFolder();

            if ($media->getMediaFolderType() == 'GROUP' && $mediaFolder && in_array($mediaFolder->getGroupId(), $this->userManager->getGroupIdsWherePermission('MEDIA_LIBRARY_ADMINISTRATION'))) {
                $this->canManageMedia[$media->getId()] = true;
            } elseif ($media->getMediaFolderType() == 'USER' && $mediaFolder) {
                if ($mediaFolder->getUserId() == $currentUser->getId()) {
                    $this->canManageMedia[$media->getId()] = true;
                } else {
                    $this->userManager->setUserById($mediaFolder->getUserId());
                    if ($this->userManager->isAdult()) {
                        $this->canManageMedia[$media->getId()] = false;
                    } else {
                        $this->canManageMedia[$media->getId()] = $this->canManageUser($mediaFolder->getUserId());
                    }
                }
            }

            //If garbaged and garbaged by current user => canManage = true
            if($media->isGarbaged() && $media->getDeletedBy() == $currentUser->getId())
            {
                $this->canManageMedia[$media->getId()] = true;
            }

            if(!isset($this->canManageMedia[$media->getId()]))
            {
                $this->canManageMedia[$media->getId()] = false;
            }
        }
        return $this->canManageMedia[$media->getId()];
    }

    /**
     * L'utilisateur set dans le resourceManager peut il administrer l'utilisateur en paramètre ?
     *
     * @param int $userId
     *
     * @return boolean
     */
    public function canManageUser($userId)
    {
        if(!isset($this->canManagerUser[$userId]))
        {
            $currentUser = $this->rightManager->getUserSession();

            if ($currentUser != null && $currentUser->getId() == $userId) {
                $this->canManagerUser[$userId] = true;
            }else{
                $this->userManager->setUser($this->rightManager->getUserSession());
                $adminUsersGroups = $this->userManager->getGroupsWherePermission('MEDIA_LIBRARY_USERS_ADMINISTRATION');
                foreach ($adminUsersGroups as $group)
                {
                    $this->getGroupManager()->setGroup($group);
                    if (in_array($userId, $this->getGroupManager()->getUsersIds())) {
                        $this->canManagerUser[$userId] = true;
                    }
                }
            }
            if(!isset($this->canManagerUser[$userId]))
            {
                $this->canManagerUser[$userId] = false;
            }
        }
        return $this->canManagerUser[$userId];
    }

    /**
     * L'utilisateur set dans le resourceManager peut il administrer le groupe en paramètre ?
     * @param int $groupId
     * @return boolean
     */
    public function canManageGroup($groupId)
    {
        if(!isset($this->canManageGroup[$groupId]))
        {
            $this->userManager->setUser($this->rightManager->getUserSession());
            $this->canManageGroup[$groupId] = in_array($groupId,$this->userManager->getGroupIdsWherePermission("MEDIA_LIBRARY_ADMINISTRATION"));
        }
        return $this->canManageGroup[$groupId];
    }

    /**
     * L'utilisateur set dans le resourceManager peut il lire le groupe en paramètre ?
     * @param int $groupId
     * @return boolean
     */
    public function canReadGroup($groupId)
    {
        if(!isset($this->canReadGroup[$groupId]))
        {
            if(isset($this->canManageGroup[$groupId]))
            {
                if($this->canManageGroup[$groupId] == true)
                {
                    $this->canReadGroup[$groupId] = true;
                    return true;
                }
            }
            try {
                $this->userManager->setUser($this->rightManager->getUserSession());
            } catch (AccessDeniedException $e){
                throw $e;
            }
            $this->canReadGroup[$groupId] = in_array($groupId, $this->userManager->getGroupIdsWherePermission('MEDIA_LIBRARY_ACCESS'));
        }
        return $this->canReadGroup[$groupId];
    }

    /**
     * L'utilisateur set dans le resourceManager peut il lire l'utilisateur en paramètre ?
     * @param int $userId
     * @return boolean
     */
    public function canReadUser($userId)
    {
        if(!isset($this->canReadUser[$userId]))
        {
            if(isset($this->canManagerUser[$userId]))
            {
                if($this->canManagerUser[$userId] == true)
                {
                    $this->canReadUser[$userId] = true;
                    return true;
                }
            }
            $this->userManager->setUser($this->rightManager->getUserSession());
            if ($this->getUser()->getId() == $userId) {
                $this->canReadUser[$userId] = $this->getRightManager()->hasRightSomeWhere('MEDIA_LIBRARY_ACCESS');
            }else{
                $groupManager = $this->getGroupManager();
                foreach ($this->userManager->getGroupsWherePermission('MEDIA_LIBRARY_ACCESS') as $group) {
                    if($group->getGroupType()->getType() == 'CLASSROOM')
                    {
                        $groupManager->setGroup($group);
                        if (in_array($userId, $groupManager->getUsersIds())) {
                            $this->canReadUser[$userId] = true;
                        }
                    }
                }
            }
            if(!isset($this->canReadUser[$userId]))
            {
                $this->canReadUser[$userId] = false;
            }
        }
        return $this->canReadUser[$userId];
    }

    public function isReadable($object)
    {
        $user = $this->getUser();

        // in worker, there is no user
        if (!$user) {
            return false;
        }

        if(!isset($this->cache['read'][$user->getId()][$object->getUniqueKey()]))
        {
            switch($object->getType())
            {
                case 'MEDIA':
                    $value = $this->canReadMedia($object);
                    break;
                case 'GROUP':
                case 'USER':
                    $value = $this->canReadFolder($object);
                    break;
            }
            $this->cache['read'][$user->getId()][$object->getUniqueKey()] = $value;
        }
        return $this->cache['read'][$user->getId()][$object->getUniqueKey()];
    }

    public function isManageable($object)
    {
        // virtual folder
        if (!$object->getId()) {
            return false;
        }

        $user = $this->getUser();

        // in worker, there is no user
        if (!$user) {
            return false;
        }

        if(!isset($this->cache['manage'][$user->getId()][$object->getUniqueKey()]))
        {
            switch($object->getType())
            {
                case 'MEDIA':
                    $value = $this->canManageMedia($object);
                    break;
                case 'GROUP':
                    if (!$this->rightManager->hasRightSomeWhere('MEDIA_LIBRARY_MY_MEDIAS')) {
                        return false;
                    }
                    if($object->getIsUserFolder())
                    {
                       $value = false;
                        break;
                    }
                    if($object->getIsExternalFolder())
                    {
                        $value = false;
                        break;
                    }
                    if ($object->getIsLocker() && $this->canReadFolder($object))
                    {
                        $value = true;
                        break;
                    }
                    $value = $this->canManageFolder($object);
                    break;
                case 'USER':
                    $value = $this->canManageFolder($object);
                    break;
            }
            $this->cache['manage'][$user->getId()][$object->getUniqueKey()] = $value;
        }
        return $this->cache['manage'][$user->getId()][$object->getUniqueKey()];
    }

    public function isWritable($object)
    {
        // virtual folder
        if (!$object->getId()) {
            return false;
        }

        $user = $this->getUser();

        // in worker, there is no user
        if (!$user) {
            return false;
        }

        if(!isset($this->cache['write'][$user->getId()][$object->getUniqueKey()]))
        {
            switch($object->getType())
            {
                case 'MEDIA':
                    if ($object->getExternalSource() && !$object->getCopyFromId()) {
                        $value = false;
                        break;
                    }
                    $value = $this->canManageMedia($object);
                    break;
                case 'GROUP':
                case 'USER':
                    /** @var MediaFolderGroup $object */
                    if($object->isRoot())
                    {
                        $value = false;
                        break;
                    }
                    if($object->getType() == 'GROUP' && $object->getIsUserFolder())
                    {
                        $value = false;
                        break;
                    }
                    if ($object->getType() == 'GROUP' && $object->getIsExternalFolder()) {
                        $value = false;
                        break;
                    }
                    if ($object->getType() == 'USER' && $object->getIsShareDestination()) {
                        $value = false;
                        break;
                    }
                    $value = $this->canManageFolder($object);
                    break;
            }
            $this->cache['write'][$user->getId()][$object->getUniqueKey()] = $value;
        }
        return $this->cache['write'][$user->getId()][$object->getUniqueKey()];
    }
}
