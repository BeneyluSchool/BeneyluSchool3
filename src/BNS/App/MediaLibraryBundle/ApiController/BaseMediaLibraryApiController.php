<?php

namespace BNS\App\MediaLibraryBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use JMS\Serializer\SerializerBuilder;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BaseMediaLibraryApiController extends BaseApiController
{
    /**
     * Vérifie l'accès à l'atelier
     */
    protected function checkMediaLibraryAccess()
    {
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasRightSomeWhere('MEDIA_LIBRARY_ACCESS'));
    }

    protected function getMediaFolder($marker)
    {
        $parts = explode('-',$marker);

        if(!is_array($parts) || count($parts) != 2)
        {
            throw new NotFoundHttpException("Le dossier ayant pour marker $marker n'existe pas");
        }

        $id = $parts[0];
        $type = $parts[1];
        $mediaFolder = $this->get('bns.media_folder.manager')->find($id, $type);
        if($mediaFolder)
        {
            return $mediaFolder;
        }else{
            throw new NotFoundHttpException('Le document ' . $type . ' - ' . $id . " n'existe pas");
        }
    }


    /////////////////   METHODES de vérification d'accès en management   \\\\\\\\\\\\\\\\\\\\\\
    /**
     * Toutes ces méthodes cascadent les unes avec les autres
     * pour arriver sur la vérification au niveau de la ressource
     */

    protected function canReadMedia(Media $media)
    {
        if (!$this->get('bns.media_library_right.manager')->canReadMedia($media)) {
            throw new AccessDeniedHttpException();
        }

        return true;
    }

    protected function canReadMediaJoined(Media $media, $objectType, $objectId)
    {
        if (!$this->get('bns.media_library_right.manager')->canReadMediaJoined($media, $objectType, $objectId)) {
            throw new AccessDeniedHttpException();
        }

        return true;
    }

    protected function canManageMedia(Media $media)
    {
        $can = $this->get('bns.media_library_right.manager')->canManageMedia($media);
        if(!$can)
        {
            throw new AccessDeniedHttpException();
        }
        return true;
    }

    protected function canWriteMedia(Media $media)
    {
        $can = $this->get('bns.media_library_right.manager')->isWritable($media);
        if(!$can)
        {
            throw new AccessDeniedHttpException();
        }
        return true;
    }

    protected function canReadMediaFolder($mediaFolder)
    {
        $can = $this->get('bns.media_library_right.manager')->canReadFolder($mediaFolder);
        if(!$can)
        {
            throw new AccessDeniedHttpException();
        }
        return true;
    }

    protected function canManageMediaFolder($mediaFolder,$forDelete = false)
    {
        if($forDelete)
        {
            $can = $this->get('bns.media_library_right.manager')->isWritable($mediaFolder);
            if (!$can) {
                throw new AccessDeniedHttpException();
            }
        }

        $can = $this->get('bns.media_library_right.manager')->canManageFolder($mediaFolder);

        if(!$can)
        {
            throw new AccessDeniedHttpException();
        }
        return true;
    }

    /**
     * Checks that current User can insert a Media in the given MediaFolder
     *
     * @param $mediaFolder
     * @return boolean
     */
    protected function canInsertMedia($mediaFolder)
    {
        try {
            // check that user can manage the folder, as usual
            $this->canManageMediaFolder($mediaFolder);
        } catch (AccessDeniedHttpException $e) {
            // maybe it's a special folder where access is allowed even without ownership
            if (!$this->get('bns.media_library_right.manager')->isManageable($mediaFolder)) {
                throw $e;
            }
        }

        return true;
    }


    //////   METHODES API  GLOBALES  \\\\\\\


    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Initialisation de la médiathèque",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-init")
     * @Rest\View(serializerGroups={"Default","list","media_list","media_detail"})
     */
    public function initAction()
    {
        $this->checkMediaLibraryAccess();
        $content = array(
            'group_folders' => array(),
        );
        $rm = $this->get('bns.right_manager');
        $this->get('bns.media.manager')->setNoMediaChildren(true);

        //Mes documents
        if($rm->hasRightSomeWhere('MEDIA_LIBRARY_MY_MEDIAS'))
        {
            $myFolder = $this->get('bns.media_folder.manager')->getUserFolder($rm->getUserSession());
            if($myFolder)
            {
                $myFolder->showRatio = true;
                $myFolder->setLabel('Mes documents');
                $content['my_folder'] = $myFolder;
            }
        }

        // get all user resources, indexed by group
        $groupsWithResources = $this->get('bns.paas_manager')->getMediaLibraryResourcesByGroup($this->getUser(), false);

        foreach($rm->getGroupsWherePermission('MEDIA_LIBRARY_ACCESS') as $group)
        {
            // group has a visible media library folder, ignore it
            if (isset($groupsWithResources[$group->getId()])) {
                unset($groupsWithResources[$group->getId()]);
            }

            if($group->getGroupType()->getType() == 'CLASSROOM')
            {
                $folder = $this->get('bns.media_folder.manager')->getGroupFolder($group);
                $folder->showRatio = true;
                array_unshift($content['group_folders'],$folder);
            }else{
                $folder = $this->get('bns.media_folder.manager')->getGroupFolder($group);
                if (in_array($group->getGroupType()->getType(), ['SCHOOL', 'PARTNERSHIP'])) {
                    $folder->showRatio = true;
                }
                $content['group_folders'][] = $folder;
            }
        }

        // some groups have resources, but no visible media library folder. Force add it
        foreach ($groupsWithResources as $groupId => $data) {
            $content['group_folders'][] = $this->get('bns.media_folder.manager')->getGroupFolder($data['group']);
        }

        // fix weird bug with serializer and non-consecutive indexes
        $content['group_folders'] = array_values($content['group_folders']);

        $userId = $this->getCurrentUserId();

        $content['garbage'] = $this->getGarbageContent();

        $content['favorites'] = $this->getFavoritesContent();

        $content['recents'] = $this->get('bns.media.manager')->getRecentsMedias();

        // Droits additionnels
        $rights = array();
        if ($rm->hasRightSomeWhere('MEDIA_LIBRARY_ADMINISTRATION') && $rm->hasRightSomeWhere('USER_DIRECTORY_ACCESS')) {
            $rights['share'] = true; // partage de documents
            $rights['back'] = true;
        }
        $content['rights'] = $rights;

        return $content;
    }

    protected function getPaasContent()
    {
        if($this->get('service_container')->hasParameter('paas_use') && $this->get('service_container')->getParameter('paas_use') === true)
        {
            $pm = $this->get('bns.paas_manager');
            return $pm->getMediaLibraryResources($this->get('bns.right_manager')->getUserSession());
        }else{
            return array();
        }

    }

    protected function getGarbageContent()
    {
        return array(
            'MEDIA_FOLDERS' => $this->get('bns.media_folder.manager')->getGarbagedMediaFolders($this->getCurrentUserId()),
            'MEDIAS' => $this->get('bns.media.manager')->getGarbagedMedias($this->getCurrentUserId())
        );
    }

    protected function getFavoritesContent()
    {
        return array(
            'MEDIA_FOLDERS' => $this->get('bns.media_folder.manager')->getFavoritesMediaFolders($this->getCurrentUserId()),
            'MEDIAS' => $this->get('bns.media.manager')->getFavoritesMedias($this->getCurrentUserId())
        );
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Contenu de la corbeille",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-corbeille")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     */
    public function garbageAction()
    {
        $this->checkMediaLibraryAccess();
        return $this->getGarbageContent();
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Vérification avant suppression de la corbeille",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-corbeille-check")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function emptyGarbageCheckAction()
    {
        $garbage = $this->getGarbageContent();

        $userId = $this->get('bns.right_manager')->getUserSessionId();

        $canDelete = array();
        $cantDelete = array();

        foreach($garbage['MEDIAS'] as $media)
        {
            if($this->canManageMedia($media))
            {
                $canDelete[] = $media;
            }else{
                $cantDelete[] = $media;
            }
        }
        foreach($garbage['MEDIA_FOLDERS'] as $mediaFolder)
        {
            if($this->canManageMediaFolder($mediaFolder))
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
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Restauration de la corbeille",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-corbeille-restore")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function garbageRestoreAction()
    {
        $garbage = $this->getGarbageContent();

        foreach($garbage['MEDIAS'] as $media)
        {
            if($this->canManageMedia($media))
            {
                $this->get('bns.media.manager')->setMediaObject($media);
                $this->get('bns.media.manager')->restore();
            }
        }
        foreach($garbage['MEDIA_FOLDERS'] as $mediaFolder)
        {
            if($this->canManageMediaFolder($mediaFolder))
            {
                $this->get('bns.media_folder.manager')->setMediaFolderObject($mediaFolder);
                $this->get('bns.media_folder.manager')->restore();
            }
        }
        return new Response(true,Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Contenu des favoris",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-favoris")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     */
    public function favoritesAction()
    {
        $this->checkMediaLibraryAccess();
        return $this->getFavoritesContent();
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Contenu des documents récents",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-recents")
     * @Rest\View(serializerGroups={"Default","list","detail"})
     */
    public function recentsAction()
    {
        $this->checkMediaLibraryAccess();
        return $this->get('bns.media.manager')->getRecentsMedias($this->getCurrentUserId());
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Contenu des documents ressources externes",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-external")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function externalAction()
    {
        $this->checkMediaLibraryAccess();
        return $this->get('bns.media.manager')->getExternalMedias($this->getCurrentUserId());
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - bases",
     *  resource = true,
     *  description = "Recherche",
     *  requirements = {
     *      {
     *          "name" = "query",
     *          "dataType" = "string",
     *          "requirement" = "",
     *          "description" = "Terme recherché"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la médiathèque",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("-recherche/{query}")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function searchAction(Request $request)
    {
        $this->checkMediaLibraryAccess();
        $query = urldecode($request->get('query'));
        //TODO faire la bonne requête

    }




}
