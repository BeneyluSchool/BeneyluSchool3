<?php

namespace BNS\App\MediaLibraryBundle\ApiController;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUserQuery;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\PaasBundle\Manager\PaasManager;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Acl\Exception\Exception;

class SelectionApiController extends BaseMediaLibraryApiController
{



    //Traite de tous les calls ayant lieu sur une sélection de documents
    //Pour rappel, les sélections sont stockés sur le poste client, pas de gestion en session associée donc

    /**
     * Tri les tableaux données en Request (distinction media Folders et Medias)
     * @param Request $request
     */
    protected function orderPostArray(Request $request)
    {
        $this->get('bns.user_manager')->setUser($this->get('bns.right_manager')->getUserSession());
        $mediaIds = array();
        $mediaFolderUserIds = array();
        $mediaFolderGroupIds = array();

        foreach($request->get('datas', array()) as $data)
        {
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            if($data['TYPE'] == "MEDIA")
            {

                if($data['ID'] > PaasManager::PAAS_OFFSET)
                {
                    //C'est une ressource PAAS, Création en local car forcément
                    $newMedia = $this->get('bns.paas_manager')->createMediaFromPaasId($data['ID'] - PaasManager::PAAS_OFFSET);
                    if($newMedia)
                    {
                        $mediaIds[] = $newMedia->getId();
                    }
                }else{
                    //C'est une ressource traditionnelle
                    $mediaIds[] = $data['ID'];
                }
            }elseif($data['TYPE'] == "MEDIA_FOLDER_USER")
            {
                $mediaFolderUserIds[] = $data['ID'];
            }elseif($data['TYPE'] == "MEDIA_FOLDER_GROUP")
            {
                $mediaFolderGroupIds[] = $data['ID'];
            }
        }
        $medias = MediaQuery::create()->findById($mediaIds);
        $mediaFolderUsers = MediaFolderUserQuery::create()->findById($mediaFolderUserIds);
        $mediaFolderGroups = MediaFolderGroupQuery::create()->findById($mediaFolderGroupIds);
        return array($medias, $mediaFolderUsers, $mediaFolderGroups);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Suppression d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-delete")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function deleteAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $userId = $this->get('bns.right_manager')->getUserSessionId();

        $nbErrors = 0;

        foreach($medias as $media)
        {
            try{
               if($this->canWriteMedia($media))
               {
                   $this->get('bns.media.manager')->setMediaObject($media);
                   $this->get('bns.media.manager')->delete($userId);
               }
            }catch(AccessDeniedHttpException $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder,true))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->delete($userId);
                }
            }catch(AccessDeniedHttpException $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder,true))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->delete($userId);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Vérification pour suppression d'une sélection, retourne un tableau de deux tableau : canDelete et cantDelete contenant les medias ou folder demandés",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-check-delete")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function checkDeleteAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $userId = $this->get('bns.right_manager')->getUserSessionId();

        $canDelete = array();
        $cantDelete = array();

        foreach($medias as $media)
        {
            if($this->canWriteMedia($media,true))
            {
                $canDelete[] = $media;
            }else{
                $cantDelete[] = $media;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            if($this->canManageMediaFolder($mediaFolder,true))
            {
                    $canDelete[] = $mediaFolder;
            }else{
                    $cantDelete[] = $mediaFolder;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            if($this->canManageMediaFolder($mediaFolder,true))
            {
                $canDelete[] = $mediaFolder;
            }else{
                $cantDelete[] = $mediaFolder;
            }
        }
        return new Response(array('canDelete' => $canDelete, 'cantDelete' => $cantDelete),Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Déplacement d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      },
     *      {
     *          "name"="parent-marker",
     *          "dataType"="string",
     *          "description"="Marqueur du dossier de destination"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-move")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function moveAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);

        $destination = $this->getMediaFolder($request->get('parent-marker'));

        $nbErrors = 0;

        //On vérifie les droits d'écriture dans la destination
        $this->canInsertMedia($destination);

        foreach($medias as $media)
        {
            try{
                if($this->canWriteMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->move($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->move($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->move($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Restauration d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-restore")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function restoreAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);

        $nbErrors = 0;

        foreach($medias as $media)
        {
            try{
                if($this->canManageMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->restore();
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->restore();
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->restore();
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Copie d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      },
     *      {
     *          "name"="parent-marker",
     *          "dataType"="string",
     *          "description"="Marqueur du dossier de destination"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-copy")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function copyAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);

        $nbErrors = 0;

        $destination = $this->getMediaFolder($request->get('parent-marker'));

        $this->canInsertMedia($destination);

        foreach($medias as $media)
        {
            try{
                if($this->canReadMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->mCopy($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->mCopy($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->mCopy($destination);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Partage d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      },
     *      {
     *          "name"="groups",
     *          "dataType"="array",
     *          "description"="Tableau d'ids des groupes de destination"
     *      },
     *      {
     *          "name"="users",
     *          "dataType"="array",
     *          "description"="Tableau d'ids des utilisateurs destinataires"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-share")
     * @Rest\View(serializerGroups={"Default","list"})
     *
     * @param Request $request
     * @return Response
     */
    public function shareAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $groupIds = $request->get('groups', array());
        $userIds = $request->get('users', array());

        $validUserIds = array();
        $validGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('MEDIA_LIBRARY_ADMINISTRATION');

        // for each target group: if valid, get ids of their users who have a media library
        foreach ($groupIds as $groupId) {
            if (in_array($groupId, $validGroupIds)) {
                $groupUsers = $this->get('bns.group_manager')->setGroupById($groupId)->getUsersByPermissionUniqueName('MEDIA_LIBRARY_MY_MEDIAS');
                $validUserIds = array_merge($validUserIds, array_keys($groupUsers));
            }
        }

        // for each target user: check that it belongs to a valid group and has a media library
        foreach ($userIds as $userId) {
            $userGroupIds = $this->get('bns.user_manager')->setUserById($userId)->getGroupIdsWherePermission('MEDIA_LIBRARY_MY_MEDIAS');
            foreach ($userGroupIds as $groupId) {
                if (in_array($groupId, $validGroupIds)) {
                    $validUserIds[] = $userId;
                    continue 2;
                }
            }
        }

        $validUserIds = array_unique($validUserIds);

        // avoid sharing to self
        if (($idx = array_search($this->getUser()->getId(), $validUserIds)) !== false) {
            unset($validUserIds[$idx]);
        }

        $nbErrors = 0;
        foreach ($medias as $media) {
            try {
                if ($this->canReadMedia($media)) {
                    $this->get('bns.media.share_manager')->shareMedia($media, $validUserIds, $this->getUser()->getId());
                }
            } catch (\Exception $e) {
                $nbErrors++;
            }
        }

        return new Response($nbErrors,Codes::HTTP_OK);
    }

    //GESTION DES FAVORIS

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Mise en favoris d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-favorite")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function favoriteAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $nbErrors = 0;
        foreach($medias as $media)
        {
            try{
                if($this->canReadMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),true);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canReadMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),true);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canReadMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),true);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Enleve le statut favori d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-unfavorite")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function unfavoriteAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $nbErrors = 0;
        foreach($medias as $media)
        {
            try{
                if($this->canReadMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canReadMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canReadMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->toggleFavorite($this->get('bns.right_manager')->getUserSessionId(),false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Mise en privé d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-private")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function privateAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $nbErrors = 0;
        foreach($medias as $media)
        {
            try{
                if($this->canManageMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->togglePrivate(true);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
           // try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->togglePrivate(true);
                }
//            }catch(\Exception $e){
//                $nbErrors++;
//            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->togglePrivate(true);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Mise en public d'une sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok + nombre d'erreurs associées",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("-unprivate")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function unprivateAction(Request $request)
    {
        //Rangement du tableau
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);
        $nbErrors = 0;
        foreach($medias as $media)
        {
            try{
                if($this->canManageMedia($media))
                {
                    $this->get('bns.media.manager')->setMediaObject($media);
                    $this->get('bns.media.manager')->togglePrivate(false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderGroups as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->togglePrivate(false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        foreach($mediaFolderUsers as $mediaFolder)
        {
            try{
                if($this->canManageMediaFolder($mediaFolder))
                {
                    $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                    $this->get('bns.media_folder.manager')->togglePrivate(false);
                }
            }catch(\Exception $e){
                $nbErrors++;
            }
        }
        return new Response($nbErrors,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Sélection",
     *  resource = true,
     *  description = "Téléchargement d'une archive de la sélection",
     *  requirements = {
     *      {
     *          "name" = "datas",
     *          "dataType" = "array",
     *          "requirement" = "",
     *          "description" = "Tableau d'ids + type de contenu associé"
     *      }
     *  }
     * )
     *
     * @Rest\Get("-archive")
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function archiveAction(Request $request)
    {
        list($medias, $mediaFolderUsers, $mediaFolderGroups) = $this->orderPostArray($request);

        $rightManager = $this->get('bns.media_library_right.manager');
        $archiveManager = $this->get('bns.media_archive.manager');

        $validMedias = array();
        foreach ($medias as $media) {
            if ($rightManager->canReadMedia($media)) {
                $validMedias[] = $media;
            }
        }

        $archivePath = $archiveManager->create($validMedias);
        $filename = 'archive.zip';

        $response = new BinaryFileResponse($archivePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('X-Filename', $filename);

        return $response;
    }


}
