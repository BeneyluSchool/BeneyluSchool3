<?php

namespace BNS\App\MediaLibraryBundle\Manager;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use \BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFavoritesQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserPeer;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaJoinObjectQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MediaManager
{


    const STATUS_ACTIVE = '1';              // Média actif et visible
    const STATUS_GARBAGED = '0';            // Média mis à la corbeille
    const STATUS_DELETED = '-1';            // Média supprimé définitivement
    const STATUS_GARBAGED_PARENT = '-2';    // Média dont le parent est à la corbeille

    // Durée en secondes de l'accessibilité temporaire
    const TEMP_DOWNLOADABLE_DURATION = '120';

    //Types de ressources supportés et leur paramètrage
    public $types = array(
        'IMAGE' => array(
            'template'		=> 'image',
            'thumbnailable' => true,
            'sizeable'		=> true
        ),
        'VIDEO' => array(
            'template'		 => 'video',
            'thumbnailable'	 => false,
            'sizeable'		 => true
        ),
        'DOCUMENT' => array(
            'template'		=> 'file',
            'thumbnailable' => false,
            'sizeable'		=> true
        ),
        'AUDIO' => array(
            'template'		=> 'audio',
            'thumbnailable' => false,
            'sizeable'		=> true
        ),
        'LINK' => array(
            'template'		=> 'link',
            'thumbnailable' => true,
            'sizeable'		=> false
        ),
        'EMBEDDED_VIDEO' => array(
            'template'		=> 'embeddedVideo',
            'thumbnailable' => true,
            'sizeable'		=> false
        ),
        'FILE' => array(
            'template'		=> 'file',
            'thumbnailable' => false,
            'sizeable'		=> true
        ),
        'ATELIER_DOCUMENT' => array(
            'template'		=> 'atelier_document',
            'thumbnailable' => false,
            'sizeable'		=> false
        ),
        'PROVIDER_RESOURCE' => array(
            'template'		=> 'provider_resource',
            'thumbnailable' => true,
            'sizeable'		=> false
        ),
        'HTML' => array(
            'template'		=> 'html',
            'thumbnailable' => true,
            'sizeable'		=> false
        ),
        'HTML_BASE' => array(
            'template'		=> 'htmlBase',
            'thumbnailable' => true,
            'sizeable'		=> false
        ),
        'ATELIER_AUDIO' => array(
            'template'		=> 'atelier_audio',
            'thumbnailable' => false,
            'sizeable'		=> false
        ),
    );

    protected $resource_file_dir;
    /** @var BNSUserManager $userManager */
    protected $userManager;
    /** @var BNSGroupManager $groupManager */
    protected $groupManager;
    /** @var Media $media */
    protected $media;
    /** @var  BNSFileSystemManager $fileSystemManager */
    protected $fileSystemManager;

    /** @var MediaLibraryRightManager */
    protected $mediaLibraryRightManager;

    protected $noMediaChildren = false;

    public function __construct($resource_file_dir, $fileSystemManager, $userManager, $groupManager, Container $container)
    {
        $this->resource_file_dir = $resource_file_dir;
        $this->fileSystemManager = $fileSystemManager;
        $this->userManager = $userManager;
        $this->groupManager = $groupManager;
        $this->encodeKey = $container->getParameter('encode_key');
        $this->container = $container;
    }

    public function setNoMediaChildren($value)
    {
        $this->noMediaChildren = $value;
    }

    public function getNoMediaChildren()
    {
        return $this->noMediaChildren;
    }

    /**
     * Set en object de la classe le media en paramètre
     * @param Media $media
     */
    public function setMediaObject($media)
    {
        $this->media = $media;
    }

    /**
     * Set de l'Object (Resource) depuis son Id
     *
     * @param $object_id
     * @throws \Exception
     */
    public function setObjectFromId($object_id)
    {
        $media = mediaQuery::create()->findOneById($object_id);
        if (!$media) {
            throw new \Exception('Media does not exist');
        }

        $this->setMediaObject($media);
    }

    /**
     * Renvoie le media
     * @return Media
     */
    public function getMediaObject()
    {
        return $this->media;
    }

    public function getFileSystem()
    {
        return $this->fileSystemManager->getFileSystem();
    }

    public function getUserManager()
    {
        return $this->userManager;
    }

    public function getGroupManager()
    {
        return $this->groupManager;
    }

    public function getMediaLibraryRightManager()
    {
        if (!$this->mediaLibraryRightManager) {
            $this->mediaLibraryRightManager = $this->container->get('bns.media_library_right.manager');
        }

        return $this->mediaLibraryRightManager;
    }

    /**
     * @param $id Id du media
     * @return Media|bool
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function find($id)
    {
        $media = MediaQuery::create()->findOneById($id);
        if(!$media)
        {
            throw new NotFoundHttpException('Media non trouvé');
        }
        $this->setMediaObject($media);
        return $media;
    }

    /////    OPERATIONS sur les objets medias    \\\\\\

    public function togglePrivate($value = null)
    {
        if($value === null)
        {
            $value = !$this->media->getIsPrivate();
        }
        $this->media->setIsPrivate($value);
        $this->media->save();
    }

    /**
     * Toggle favori pour l'utilisateur placé en paramètre
     * @param $userId
     * @param $value si on souhaite forcer la valeur
     */
    public function toggleFavorite($userId, $value = null)
    {
        $favorite = MediaFavoritesQuery::create()
            ->filterByUserId($userId)
            ->filterByMediaId($this->media->getId())
            ->findOneOrCreate();

        if($favorite->isNew() || $value == true)
        {
            $favorite->save();
        }else{
            $favorite->delete();
        }
    }

    /**
     * Fonction de suppression : en deux étapes, toujours en soft delete
     */
    public function delete($userId, $status = null, $fromParent = false)
    {
        if($status == null)
        {
            $status = $this->media->getStatusDeletion();
        }

        switch($status)
        {
            case self::STATUS_ACTIVE:
                if ($fromParent) {
                    $this->media->setStatusDeletion(self::STATUS_GARBAGED_PARENT);
                } else {
                    $this->media->setStatusDeletion(self::STATUS_GARBAGED);
                }
                $this->media->setDeletedBy($userId);
                $this->media->save();
                //Pas d'autre traitement, il n'y a pas de libération d'espace
                break;
            case self::STATUS_GARBAGED:
            case self::STATUS_GARBAGED_PARENT:
            case self::STATUS_DELETED:
                // média était déjà supprimé, son parent devient supprimé => simple mise à jour de son statut
                if (self::STATUS_GARBAGED == $status && $fromParent) {
                    $this->media->setStatusDeletion(self::STATUS_GARBAGED_PARENT);
                    $this->media->setDeletedBy($userId);
                    $this->media->save();
                    break;
                }

                $this->media->setStatusDeletion(self::STATUS_DELETED);
                $this->media->setDeletedBy($userId);
                $this->media->save();
                //Libération de l'espace disque associé
                $folder = $this->getMediaObject()->getMediaFolder();
                if($folder->getType() == 'GROUP')
                {
                    //On libère sur le groupe
                    $folder->getGroup()->deleteResourceSize($this->media->getSize(false));
                }elseif($folder->getType() == 'USER'){
                    //Ou on libère sur l'utilisateur
                    $folder->getUser()->deleteResourceSize($this->media->getSize(false));
                }
                break;
        }
    }

    /**
     * Fonction de restauration
     */
    public function restore()
    {
        $this->media->setStatusDeletion(self::STATUS_ACTIVE);
        $this->media->setDeletedBy(null);
        $this->media->save();
    }

    /**
     * Renvoie les documents supprimés pour l'utilisateur donné en paramètre
     */
    public function getGarbagedMedias($userId)
    {
        return MediaQuery::create()
            ->filterByStatusDeletion(self::STATUS_GARBAGED)
            ->filterByDeletedBy($userId)
            ->find();
    }

    /**
     * Renvoie les documents supprimés pour l'utilisateur donné en paramètre
     */
    public function getFavoritesMedias($userId)
    {
        return MediaQuery::create()
            ->filterByStatusDeletion(self::STATUS_ACTIVE)
            ->useMediaFavoritesQuery()
                ->filterByUserId($userId)
            ->endUse()
            ->find();
    }

    public function isFavorite($userId)
    {
        if(!isset($this->favoritesCache[$userId]))
        {
            $this->favoritesCache[$userId] = MediaFavoritesQuery::create()->filterByUserId($userId)->select('media_id')->find()->toArray();
        }
        return in_array($this->getMediaObject()->getId(),$this->favoritesCache[$userId]);
    }

    /**
     * Renvoie les documents récents pour l'utilisateur donné en paramètre
     */
    public function getRecentsMedias()
    {
        $container = BNSAccess::getContainer();
        $rm = $container->get('bns.right_manager');
        $gm = $container->get('bns.group_manager');
        $mlrm = $container->get('bns.media_library_right.manager');

        $authorisedUsers[] = $rm->getUserSessionId();

        if($rm->hasRightSomeWhere('MEDIA_LIBRARY_USERS_ADMINISTRATION'))
        {
            foreach($rm->getGroupsWherePermission('MEDIA_LIBRARY_USERS_ADMINISTRATION') as $group)
            {
                $gm->setGroup($group);
                $users = $gm->getUsersByPermissionUniqueName('MEDIA_LIBRARY_MY_MEDIAS',true);
                foreach($users as $user)
                {
                    $authorisedUsers[] = $user->getId();
                }
            }
        }else{
            //TODO traiter cas pour les élèves des dossiers des camarades

        }

        //Attention on remonte les privés, mais du coup pou un élève on ne voit pas les documents récents des camarades, du tout
//        $userFoldersIds = MediaFolderUserQuery::create()
//            ->select(MediaFolderUserPeer::ID)
//            ->filterByStatusDeletion(MediaFolderManager::STATUS_ACTIVE)
//            ->filterByUserId($authorisedUsers)
//            ->find()->toArray();

        //On ne remonte que les dossiers publics
        //TODO remonter les dossiers privés "si on a le droit"

        $authorisedGroupsIds = $rm->getGroupIdsWherePermission('MEDIA_LIBRARY_ACCESS');

        $groupsFoldersIds = MediaFolderGroupQuery::create()
            ->select(MediaFolderGroupPeer::ID)
            ->filterByIsPrivate(false)
            ->filterByStatusDeletion(MediaFolderManager::STATUS_ACTIVE)
            ->filterByGroupId($authorisedGroupsIds)
            ->find()->toArray();


//        $mediaUsers = MediaQuery::create()
//            ->filterByStatusDeletion(self::STATUS_ACTIVE)
//            ->filterByIsPrivate(false)
//            ->filterByMediaFolderType('USER')
//            ->filterByMediaFolderId($authorisedUsers)
//            ->limit(30)
//            ->find();

        $mediaGroups = MediaQuery::create()
            ->filterByStatusDeletion(self::STATUS_ACTIVE)
            ->filterByExternalSource(null, \Criteria::ISNULL)
            ->filterByIsPrivate(false)
            ->filterByMediaFolderType('GROUP')
            ->filterByMediaFolderId($groupsFoldersIds)
            ->limit(30)
            ->find();

        return $mediaGroups;


/*
        return MediaQuery::create()
            ->filterByStatusDeletion(self::STATUS_ACTIVE)
            ->filterByIsPrivate(false)
            ->orderByUpdatedAt(\Criteria::DESC)
                ->condition('user_folder_id', 'media.MediaFolderId IN ?', $userFoldersIds)
                ->condition('user_folder_type', 'media.MediaFolderType = ?', 'USER')
            ->combine(array('user_folder_id', 'user_folder_type'), 'and', 'user_folder_condition')
                ->condition('group_folder_id', 'media.MediaFolderId IN ?', $groupsFoldersIds)
                ->condition('group_folder_type', 'media.MediaFolderType = ?', 'GROUP')
            ->combine(array('group_folder_id', 'group_folder_type'), 'and', 'group_folder_condition')
            ->combine(array('user_folder_condition', 'group_folder_condition'), 'and', 'folder_condition')
            ->limit(60)
            ->find();
*/
    }

    public function getExternalMedias($userId)
    {
        return $this->container->get('bns.paas_manager')->getMediaLibraryResources(UserQuery::create()->findOneById($userId));
    }

    /**
     * Various tasks on newly-inserted medias
     *
     * @throws \Exception
     * @throws \PropelException
     */
    public function doPostInsert()
    {
        $media = $this->getMediaObject();
        $folder = $media->getMediaFolder();

        // if inserted in a locker, must rename it and make it private
        if ('GROUP' === $folder->getType() && $folder->getIsLocker()) {
            $author = $media->getUserRelatedByUserId();
            $labels = array(
                mb_strtolower($author->getLastName(), 'UTF-8'),
                mb_strtolower($author->getFirstName(), 'UTF-8'),
                date('d-m-H\hi')
            );
            $label = implode('-', $labels);
            $media->setLabel(str_replace(' ', '-', $label));
            $media->setIsPrivate(true);
            $media->save();
        }
    }

    /**
     * Déplace dans le media folder destination
     */
    public function move($mediaFolder, $new = false, $force = false)
    {
        //Ajout de l'espace
        if(!$force && BNSAccess::isConnectedUser())
        {
            BNSAccess::getContainer()->get('bns.media.creator')->checkSize($mediaFolder, $this->getMediaObject()->getSize(false), $this->getUserManager()->getUser()->getId());
        }
        $oldMediaFolder = $this->getMediaObject()->getMediaFolder();
        $media = $this->getMediaObject();
        $size = $media->getSize(false);
        //Suppression de l'espace
        if(!$new && BNSAccess::isConnectedUser())
        {
            BNSAccess::getContainer()->get('bns.media.creator')->removeSize($oldMediaFolder, $size);
        }
        $media->setMediaFolderId($mediaFolder->getId());
        $media->setMediaFolderType($mediaFolder->getType());
        $media->save();

        $this->doPostInsert();

        //Ajout de l'espace
        if(BNSAccess::isConnectedUser())
        {
            BNSAccess::getContainer()->get('bns.media.creator')->addSize($mediaFolder, $size);
        }

    }

    public function mCopy($mediaFolder, $userId = null)
    {
        //Vérification de l'espace
        BNSAccess::getContainer()->get('bns.media.creator')->checkSize($mediaFolder, $this->getMediaObject()->getSize(false), $this->getUserManager()->getUser()->getId());
        /** @var MediaFolderGroup $mediaFolder */
        $copied = $this->getMediaObject()->copy();
        $copied->setMediaFolderId($mediaFolder->getId());
        $copied->setMediaFolderType($mediaFolder->getType());
        $copied->setSize( $this->getMediaObject()->getSize(false));
        if ($userId) {
            $copied->setUserId($userId);
        }
        $copied->save();
        if($this->getFileSystem()->has($this->getMediaObject()->getFilePath()))
        {
            $this->getFileSystem()->write($copied->getFilePath(),$this->getFileSystem()->read($this->getMediaObject()->getFilePath()));
        }
        //Ajout de l'espace
        BNSAccess::getContainer()->get('bns.media.creator')->addSize($mediaFolder, $this->getMediaObject()->getSize(false));

        $original = $this->getMediaObject();
        $this->setMediaObject($copied);
        $this->doPostInsert();
        $this->setMediaObject($original);

        if ($original->getWorkshopDocumentId()) {
            $this->container->get('bns.workshop.document.manager')->copy($original->getWorkshopDocumentId(), $copied);
        } else if ($original->isWorkshopAudio()) {
            $copiedContent = $this->container->get('bns.workshop.content.manager')->copy($original->getWorkshopContent());
            $copiedContent->setWorkshopAudio($original->getWorkshopContent()->getWorkshopAudio()->copy());
            $copiedContent->setMedia($copied);
            $copiedContent->save();
        }

        return $copied;
    }


    //////////////////////     Fonctions de lecture     \\\\\\\\\\\\\\\\\\\\\\\\


    /*
     * Lit un fichier depuis le fileSystem
     * @param $size : taille si image ou contenu ayant une image
     * @param $encoded : pour les images, renvoyer le contenu 'base64' pour affichage sans requettage
     */
    public function read($size = null, $encoded = false)
    {
        if (!$encoded) {
            $path = $this->getMediaObject()->getFilePath($size);
        } else {
            $path = $this->getMediaObject()->getEncodedContentPath($size);
        }
        if ($this->getFileSystem()->has($path)) {
            return $this->getFileSystem()->read($path);
        } /*elseif (null !== $size && 'original' !== $size && $this->isThumbnailable()) {
            // On créé à la volé les miniature
            BNSAccess::getContainer()->get('bns.media.creator')->createThumb($size);
            if ($this->getFileSystem()->has($path)) {
                return $this->getFileSystem()->read($path);
            }
        }*/
        return false;
    }

    /**
     * @return string
     */
    public function getTempUrl()
    {
        $validity = date('U') + self::TEMP_DOWNLOADABLE_DURATION;

        //TODO : faire matcher l'url de DL
        return BNSAccess::getContainer()->get('router')->generate('resource_file_download_temporary', array(
                'validity'		 => $validity,
                'resource_slug'	 => $this->getMediaObject()->getSlug(),
                'key'			 => Crypt::encode($validity . $this->getObject()->getSlug() . $this->encode_key)
            ),true
        );
    }

    public function getTempDir()
    {
        return $this->fileSystemManager->getTempDir();
    }

    /**
     * @param type $key
     * @param type $validity
     *
     * @return string
     */
    public function checkTempUrlKey($key, $validity)
    {
        return Crypt::encode($validity . $this->getMediaObject()->getSlug() . $this->encode_key) == $key;
    }

    /**
     * Retour une image selon le type de ressource
     * @return string path
     */
    public function getFileTypeImageUrl($size)
    {
        return BNSAccess::getContainer()->get('templating.helper.assets')->getUrl('/medias/images/resource/filetype/' . $size . '/' . strtolower($this->getMediaObject()->getTypeUniqueName()) . '.png');
    }

    /*
     * Renvoie le chemin local du fichier : on lit avant le fichier pour être certain de sa présence en local
     * @return $path
     */
    public function getAbsoluteFilePath($size = null)
    {
        $path = $this->resource_file_dir . '/' . $this->getMediaObject()->getFilePath($size);
        if(!is_file($path)){
            //On rappatrie ainsi le fichier en local
            $this->read($size);
        }

        return $this->resource_file_dir . '/' . $this->getMediaObject()->getFilePath($size);
    }


    /*
     * Renvoie "l'url" en data-64 pour les images
     * @return $path
     */
    public function getDataUrl($size = null)
    {
        //On rappatrie ainsi le fichier en local
        $media = $this->getMediaObject();

        if ($this->getFileSystem()->has($this->getMediaObject()->getFilePath($size))) {
            return 'data:' . $media->getFileMimeType() . ';base64,' . $this->getEncodedImageContent($size);
        } else if ($this->read($size)) {
            return $this->getDataUrl($size);
        } else {
            return $this->getFileTypeImageUrl($size);
        }
    }

    public function getEncodedImageContent($size = null)
    {
        if(!$this->getFileSystem()->has($this->getMediaObject()->getEncodedContentPath($size))){
            $this->getFileSystem()->write($this->getMediaObject()->getEncodedContentPath($size), base64_encode($this->read($size)));
        }
        return $this->read($size, true);
    }


    //////////////   Fonctions liées à la taille des médias   \\\\\\\\\\\\\\\

    /*
     * Ajoute la taille de la ressource à l'espace "strong"
     * @param $resource Resource
     * @param $size String la taille
     */
    public function addSize($size = null, $label = null)
    {
        if ($size == null) {
            $size = $this->getSize($this->getMediaObject()->getSize(false));
        }

        $folder = $this->getMediaObject()->getMediaFolder();

        if ($folder->getType() == 'USER') {
            $folder->getUser()->addResourceSize($size);
        }elseif($$folder->getType() == 'GROUP') {
            $folder->getGroup()->addResourceSize($size);
        }
    }

    /*
     * Enlève la taille de la ressource à l'espace "strong"
     * @param $resource Resource
     * @param $size String la taille
     *
     * @deprecated unused to be removed
     */
    public function deleteSize($size = null)
    {
        if ($size == null) {
            $size = $this->getSize($this->getMediaObject()->getSize(false));
        }
        $folder = $this->getMediaObject()->getMediaFolder();
        if ($folder->getType() == 'USER') {
            $folder->getUser()->deleteResourceSize($size);
        } elseif ($folder->getType() == 'GROUP') {
            $folder->getGroup()->deleteResourceSize($size);
        }
    }

    /*
     * La resource est elle "sizeable" (typiquement un fichier l'est, un lien ne l'est pas)
     * @param $resource Resource
     * @return boolean
     */
    public function isSizeable()
    {
        return $this->types[$this->getMediaObject()->getTypeUniqueName()]['sizeable'];
    }

    /*
     * Renvoie la taille
     */
    public function getSize(){
        if($this->isSizeable())
        {
            return $this->getMediaObject()->getSize(false);
        }
        return 0;
    }

    public function isThumbnailable()
    {
        if (!$this->getMediaObject()) {
            return false;
        }
        return $this->types[$this->getMediaObject()->getTypeUniqueName()]['thumbnailable'] || $this->getMediaObject()->getFromPaas();
    }

    //////////////  Fonctions liées aux pièces jointes    \\\\\\\\\\\\\

    //TODO ALL

    public function bindAttachments($object,$request){
        if(!$object || !$request){
            throw new Exception('Attachements can not be binded : please provide request and object');
        }

        $object->attachments = null;

        $attachements = $request->get('resource-joined');
        if(is_array($attachements)){
            $mediaLibraryRightManager = $this->getMediaLibraryRightManager();
            $medias = MediaQuery::create()->findById($attachements);
            if($medias){
                foreach($medias as $media){
                    if($mediaLibraryRightManager->canReadMedia($media)){
                        $object->attachments[] = $media;
                    }
                }
            }
        }
    }

    public function saveAttachments($object, $request, $links = null){
        if (!$object || !$request){
            throw new \Exception('Attachements can not be saved : please provide request and object');
        }

        $objectIds = MediaJoinObjectQuery::create()
            ->filterByObjectId($object->getPrimaryKey())
            ->filterByObjectClass($object->getMediaClassName())
            ->filterByIsEmbedded(false)
            ->select(array('ObjectId'))
            ->find()
            ->getArrayCopy();

        if ($request instanceof Request) {
            $attachments = $request->get('resource-joined');
        } else {
            $attachments = $request;
        }
        if (is_array($attachments)) {
            $mediaLibraryRightManager = $this->getMediaLibraryRightManager();
            $medias = MediaQuery::create()->findById($attachments);

            $removeIds = array_diff($objectIds, $medias->getPrimaryKeys());
            $keepIds = array_diff($medias->getPrimaryKeys(), $objectIds);

            MediaJoinObjectQuery::create()
                ->filterByObjectId($removeIds)
                ->filterByObjectClass($object->getMediaClassName())
                ->filterByIsEmbedded(false)
                ->delete();

            if ($medias) {
                foreach ($medias as $media) {
                    if (in_array($media->getId(), $keepIds) || $mediaLibraryRightManager->canReadMedia($media)) {
                        $attachment = $object->addResourceAttachment($media->getId());
                        if($links){
                            if (!is_array($links)) {
                                $links = array($links);
                            }
                            foreach ($links as $link) {
                                if ($link->getClassName() == 'User') {
                                    $object->addResourceAttachmentsLinkUsers($attachment->getId(), $link->getId());
                                } elseif ($link->getClassName() == 'Group') {
                                    $object->addResourceAttachmentsLinkGroups($attachment->getId(), $link->getId());
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $object->deleteAllResourceAttachments();
        }
    }

    public function getAttachmentsId($request){

        $attachementsId = $request->get('resource-joined');

        return $attachementsId;
    }

    public function generateVisualizeHash($media)
    {
        $params = array(
            $media->getId(),
            $media->getUserId(),
            $media->getSlug()
        );
        return urlencode(Crypt::encrypt(join('___', $params), $this->encodeKey));
    }

}
