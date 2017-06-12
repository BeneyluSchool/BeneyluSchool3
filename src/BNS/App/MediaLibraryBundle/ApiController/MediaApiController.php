<?php

namespace BNS\App\MediaLibraryBundle\ApiController;

use BNS\App\CoreBundle\Model\ActivityQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\PaasBundle\Manager\PaasManager;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


class MediaApiController extends BaseMediaLibraryApiController
{
    /////////////////   METHODES de vérification d'accès     \\\\\\\\\\\\\\\\\\\\\\
    /**
     * Toutes ces méthodes cascadent les unes avec les autres
     * pour arriver sur la vérification au niveau de la ressource
     */



    protected function getMedia($id)
    {
        return $this->get('bns.media.manager')->find($id);
    }

    protected function getMediaManager($id)
    {
        $this->getMedia($id);
        return $this->get('bns.media.manager');
    }

    protected function buildResponse($media, $httpCode = Codes::HTTP_OK)
    {
        //Création de la réponse
        $response = new Response('', $httpCode);
        if ($media) {
            $response->headers->set('Location', $this->generateUrl('media_api_get', array(
                'version' => $this->getVersion(),
                'id'      => $media->getId()
            )));
        }

        return $response;
    }

    /////////////////   METHODES REST  \\\\\\\\\\\\\\\\\\

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Media",
     *  resource = true,
     *  description = "Détails d'un document de la médiathèque",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès au document",
     *      404 = "Le document n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function getAction(Request $request, $id)
    {
        $media = null;
        $canRead = false;
        if (is_numeric($id)) {
            $media = MediaQuery::create()->findPk($id);
            if (!$media && $id > PaasManager::PAAS_OFFSET) {
                // TODO identify paas ressource
            }
        } else {
            list($groupId, $activityUniqueName, $mediaSource) = explode('-', $id);
            $group = GroupQuery::create()->findPk($groupId);

            if (!$mediaSource) {
                $groupActivity = $this->get('bns_paas.activity_manager')->getActivity($activityUniqueName, $group);
                if (!$groupActivity) {
                    return $this->view('', Codes::HTTP_NOT_FOUND);
                }
                $activity = $groupActivity->getActivity();
                $resource = $this->get('bns.paas_manager')->getResourceFromActivity($activity, $group);
                $media = $this->get('bns.paas_manager')->hydrateResourceFromPaas($resource);
            } elseif ('nr' == $mediaSource) {
                $resources = $this->get('bns_app_paas.manager.nathan_resource_manager')->getResources($this->getUser(), $group, 'nathan', $activityUniqueName);
                if (count($resources)) {
                    $canRead = true;
                    $media = reset($resources);
                }
            }
        }

        if (!$media) {
            return $this->view('', Codes::HTTP_NOT_FOUND);
        }

        if ($media->isFromPaas()) {
            $this->get('bns.paas_manager')->refreshMedia($media);
        } else {
            $original = $media->getOriginal();
            if ($original && $original->isFromPaas()) {
                $this->get('bns.paas_manager')->refreshMedia($original);
            }
        }

        if ($media->hasExpired()) {
            // TODO: a more explicit error?
            return $this->view('MEDIA_EXPIRED', Codes::HTTP_NOT_FOUND);
        }

        if (!$canRead) {
            if ($request->query->has('objectType') && $request->query->has('objectId')) {
                $this->canReadMediaJoined($media, $request->get('objectType'), (int) $request->get('objectId'));
            } else {
                $this->canReadMedia($media);
            }
        }
        if ($media->isActive()) {
            return $media;
        }

        throw new NotFoundHttpException("Ce document n'est pas accessible.");
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Media",
     *  resource=true,
     *  description="Création d'un document de la médiathèque à partir d'un fichier",
     *  statusCodes = {
     *      201 = "Document créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à la médiathèque"
     *   },
     *   requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du parent (id-type)"
     *      }
     *   },
     *   parameters= {
     *      {"name"="label", "dataType"="string", "required"=true, "description"="Nom du document"},
     *      {"name"="description", "dataType"="string", "required"=false, "description"="Description du document"},
     *      {"name"="filename", "dataType"="string", "required"=true, "description"="TODO pour récupérer le fichier"}
     *   }
     * )
     * @Rest\Post("/{marker}/file")
     * @Rest\View(serializerGroups={"detail"})
     */
    public function postFileAction($marker, Request $request)
    {
        $this->checkMediaLibraryAccess();
        $mediaFolder = $this->getMediaFolder($marker);
        if ($this->canInsertMedia($mediaFolder)) {
            // TODO use symfony form + file validator
            try {
                $creator = $this->get('bns.media.creator');
                $mediaArray = $creator->createFromRequest($mediaFolder, $this->get('bns.right_manager')->getUserSessionId(), $request);

                // TODO : do this the right way handle multiple files
                if (isset($mediaArray[0])) {
                    $media = $mediaArray[0];

                    // user can't see content => do not send media info
                    if (!$this->get('bns.media_library_right.manager')->canReadFolderContent($mediaFolder)) {
                        $media = null;
                    }

                    return $this->buildResponse($media, Codes::HTTP_CREATED);
                }
                // no file in the array maybe php max upload limit exceeded
                throw new FileException('UPLOAD_ERR_INI_SIZE');

            } catch(FileException $e) {
                // TODO : Use a custom Exception and refactor all this mess
                $code = $e->getMessage();
                if (in_array($code, array('UPLOAD_ERR_INI_SIZE','UPLOAD_ERR_FORM_SIZE'))) {
                    $maxSize = $this->get('bns.media.creator')->getMaxUploadSize();

                    return new JsonResponse(array('error_code' => 'ERROR_FILE_IS_TOO_LARGE', 'max_size' => $maxSize), Codes::HTTP_BAD_REQUEST);
                }

                return new JsonResponse(array('error_code' => $e->getMessage()), Codes::HTTP_BAD_REQUEST);
            } catch (\Exception $e) {
                return new JsonResponse(array('error_code' => 'INVALID', ), Codes::HTTP_BAD_REQUEST);
            }
        }

        throw new AccessDeniedHttpException('Forbidden Action');
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Media",
     *  resource=true,
     *  description="Copie d'un document de le répertoire personnel",
     *  statusCodes = {
     *      201 = "Document créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à la médiathèque"
     *   },
     *   requirements = {
     *      {
     *          "name" = "mediaId",
     *          "dataType" = "integer",
     *          "description" = "L'id du document"
     *      }
     *   }
     * )
     * @Rest\Post("/{mediaId}/file-my-copy")
     * @Rest\View(serializerGroups={"detail"})
     */
    public function postMediaCopyForUserAction($mediaId, Request $request)
    {
        $this->checkMediaLibraryAccess();
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasRightSomeWhere('MEDIA_LIBRARY_MY_MEDIAS'));
        $userFolder = $this->get('bns.user_manager')->getUser()->getMediaFolder();
        $this->canManageMediaFolder($userFolder);
        $media = $this->get('bns.media.manager')->find($mediaId);
        $this->canReadMedia($media);
        $copied = $this->get('bns.media.manager')->mCopy($userFolder);
        return $this->buildResponse($copied, Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Media",
     *  resource=false,
     *  description="Création d'un document de la médiathèque à partir d'une URL",
     *  statusCodes = {
     *      201 = "Document créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à la médiathèque"
     *   },
     *   requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du parent (id-type)"
     *      }
     *   },
     *   parameters= {
     *      {"name"="label", "dataType"="integer", "required"=true, "description"="Nom du document"},
     *      {"name"="description", "dataType"="string", "required"=false, "description"="Description du document"},
     *      {"name"="url", "dataType"="string", "required"=true, "description"="Url du document"}
     *   }
     * )
     * @Rest\Post("/{marker}/url")
     * @Rest\View(serializerGroups={"detail"})
     */
    public function postUrlAction($marker, Request $request)
    {
        $this->checkMediaLibraryAccess();
        $mediaFolder = $this->getMediaFolder($marker);
        if($this->canInsertMedia($mediaFolder))
        {
            try{
                $creator = $this->get('bns.media.creator');
                $media = $creator->createFromUrl($mediaFolder, $this->get('bns.right_manager')->getUserSessionId(), $request->get('url'));

                // user can't see content => do not send media info
                if (!$this->get('bns.media_library_right.manager')->canReadFolderContent($mediaFolder)) {
                    $media = null;
                }

                return $this->buildResponse($media, Codes::HTTP_CREATED);
            }catch(FileException $e){
                return new Response($e->getMessage(),Codes::HTTP_INTERNAL_SERVER_ERROR);
            }
        }else{
            throw new AccessDeniedHttpException('Forbidden Action');
        }
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Media",
     *  resource=false,
     *  description="Met à jour un média",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function editAction(Media $media)
    {
        $this->canManageMedia($media);

        return $this->restForm('api_media', $media, array(
            // TODO fix this
            'csrf_protection' => false,
        ));
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Media",
     *  resource=false,
     *  description="Met à jour un média - toggle favori",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{id}/toggle-favorite")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function toggleFavoriteAction(Media $media)
    {
        $mediaManager = $this->getMediaManager($media->getId());
        $this->canReadMedia($media);
        $mediaManager->toggleFavorite($this->get('bns.right_manager')->getUserSessionId());
        return $mediaManager->getMediaObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Media",
     *  resource=false,
     *  description="Met à jour un média - toggle privé",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{id}/toggle-private")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function togglePrivateAction(Media $media)
    {
        $mediaManager = $this->getMediaManager($media->getId());
        $this->canManageMedia($media);
        $mediaManager->togglePrivate();
        return $mediaManager->getMediaObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Media",
     *  resource=false,
     *  description="Met à jour un média - déplacement",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  parameters = {
     *      {
     *          "name" = "parent-marker",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "Le marqueur du futur dossier parent"
     *      },
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{id}/move")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function moveAction(Media $media, Request $request)
    {
        $mediaManager = $this->getMediaManager($media->getId());
        $this->canManageMedia($media);
        $mediaFolder = $this->getMediaFolder($request->get('parent-marker'));
        $this->canInsertMedia($mediaFolder);
        $mediaManager->move($mediaFolder);

        // user can't see content => do not send media info
        if (!$this->get('bns.media_library_right.manager')->canReadFolderContent($mediaFolder)) {
            return null;
        }

        return $mediaManager->getMediaObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Media",
     *  resource=false,
     *  description="Met à jour un média - restauration",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id du document"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{id}/restore")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function restoreAction(Media $media, Request $request)
    {
        $mediaManager = $this->getMediaManager($media->getId());
        $this->canManageMedia($media);
        $mediaManager->restore();
        return $mediaManager->getMediaObject();
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Media",
     *  resource=true,
     *  description="Suppression d'un document",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id de l'objet"
     *      }
     *  }
     * )
     * @Rest\Delete("/{id}")
     */
    public function deleteAction(Media $media)
    {
        $mediaManager = $this->getMediaManager($media->getId());
        $this->canManageMedia($media);
        $mediaManager->delete($this->get('bns.right_manager')->getUserSessionId());
        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }
}
