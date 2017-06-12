<?php

namespace BNS\App\MediaLibraryBundle\ApiController;

use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use FOS\RestBundle\Controller\Annotations as Rest;

class MediaFolderApiController extends BaseMediaLibraryApiController
{
    /////////////////   METHODES de vérification d'accès     \\\\\\\\\\\\\\\\\\\\\\

    protected function getMediaFolderManager($marker)
    {
        $this->getMediaFolder($marker);
        return $this->get('bns.media_folder.manager');
    }

    protected function buildResponse($mediaFolder, $httpCode = Codes::HTTP_OK)
    {
        //Création de la réponse
        $response = new Response('', $httpCode);
        $response->headers->set('Location', $this->generateUrl('media_folder_api_get', array(
            'version' => $this->getVersion(),
            'marker'  => $mediaFolder->getMarker()
        )));
        return $response;
    }

    /////////////////   METHODES REST  \\\\\\\\\\\\\\\\\\

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Dossier de médias",
     *  resource = true,
     *  description = "Détails d'un dossier de la médiathèque",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du parent (id-type)"
     *      }
     *   },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès au dossier",
     *      404 = "Le dossier n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Get("/{marker}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function getAction($marker)
    {
        $mediaFolder = $this->getMediaFolder($marker);
        $mediaFolder->showRatio = true;
        try {
            $this->canReadMediaFolder($mediaFolder);
        } catch (AccessDeniedHttpException $e) {
            // allow access to invisible group folders with external resources
            $canSee = false;

            if ('GROUP' === $mediaFolder->getType() && $mediaFolder->isRoot()) {
                $userManager = $this->get('bns.user_manager')->setUser($this->getUser());
                // 1. check that user has media library access
                if ($userManager->hasRightSomeWhere('MEDIA_LIBRARY_ACCESS')) {
                    $groupsAndRoles = $userManager->getSimpleGroupsAndRolesUserBelongs();
                    // 2. check that user belongs to the group
                    if (isset($groupsAndRoles[$mediaFolder->getGroupId()])) {
                        // 3. check that group actually has resources
                        $resources = $this->get('bns.paas_manager')->getResources($mediaFolder->getGroup());
                        if (count($resources)) {
                            $canSee = true;
                        }
                    }
                }
            }

            if (!$canSee) {
                throw $e;
            }
        }
        if ($mediaFolder instanceof MediaFolderGroup && $mediaFolder->getIsExternalFolder()) {
            // refresh external medias
            $mediaFolder->setMedias($this->get('bns.paas_manager')->getMediaLibraryResources($this->getUser(), $mediaFolder->getGroup()));
        }

        return $mediaFolder;
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Dossier de médias",
     *  resource=true,
     *  description="Création d'un dossier de médiathèque",
     *  statusCodes = {
     *      201 = "Dossier créé",
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
     *      {"name"="label", "dataType"="string", "required"=true, "description"="Nom du dossier"}
     *   },
     * )
     * @Rest\Post("/{marker}")
     * @Rest\View(serializerGroups={"detail"})
     */
    public function postFileAction($marker, Request $request)
    {
        $this->checkMediaLibraryAccess();

        //Récupération et vérification des droits sur le parent
        $parent = $this->getMediaFolder($marker);
        $this->canManageMediaFolder($parent);

        // Création via Manager
        $mediaFolder = $this->get('bns.media_folder.manager')
            ->create(
                $request->get('label'),
                $parent->getId(),
                $parent->getType()
            );

        return $this->buildResponse($mediaFolder, Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Renommer",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  },
     *  parameters = {
     *      {
     *          "name" = "label",
     *          "required" = true,
     *          "dataType" = "string",
     *          "description" = "Le nouveau nom du dossier"
     *      },
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{marker}/rename")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function renameAction($marker, Request $request)
    {
        $mediaFolder = $this->getMediaFolder($marker);
        $this->canManageMediaFolder($mediaFolder);
        if($request->get('label') != '')
        $mediaFolder->setLabel($request->get('label'));
        $mediaFolder->save();
        return $mediaFolder;
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Toggle privé",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{marker}/toggle-private")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function togglePrivateAction($marker)
    {
        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $this->canManageMediaFolder($mediaFolderManager->getMediaFolderObject());
        $mediaFolderManager->togglePrivate();
        return $mediaFolderManager->getMediaFolderObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Toggle favori",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{marker}/toggle-favorite")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function toggleFavoriteAction($marker)
    {
        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $this->canReadMediaFolder($mediaFolderManager->getMediaFolderObject());
        $mediaFolderManager->toggleFavorite($this->get('bns.right_manager')->getUserSessionId());
        return $mediaFolderManager->getMediaFolderObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Toggle casier",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{marker}/toggle-locker")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function toggleLockerAction($marker, Request $request)
    {
        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $this->canManageMediaFolder($mediaFolderManager->getMediaFolderObject());
        $mediaFolderManager->toggleLocker();
        return $mediaFolderManager->getMediaFolderObject();
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Déplacement",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier enfant (id-type)"
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
     * @Rest\Patch("/{marker}/move")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function moveAction($marker, Request $request)
    {
        $parentMarker = $request->get('parent-marker');
        $mediaFolderManager = $this->getMediaFolderManager($parentMarker);
        $parent = $mediaFolderManager->getMediaFolderObject();
        $this->canManageMediaFolder($parent);

        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $child = $mediaFolderManager->getMediaFolderObject();
        $this->canManageMediaFolder($child);
        $mediaFolderManager->move($parent);

        return $child;
    }

    /**
     * @ApiDoc(
     *  section="Médiathèque - Dossier de médias",
     *  resource=false,
     *  description="Met à jour un dossier de média - Restauration",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le média n'a pas été trouvé"
     *  }
     * )
     * @Rest\Patch("/{marker}/restore")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function restoreAction($marker, Request $request)
    {
        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $this->canManageMediaFolder($mediaFolderManager->getMediaFolderObject());
        $mediaFolderManager->restore();
        return $mediaFolderManager->getMediaFolderObject();
    }

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Dossier de médias",
     *  resource=true,
     *  description="Suppression d'un dossier",
     *  requirements = {
     *      {
     *          "name" = "marker",
     *          "dataType" = "string",
     *          "description" = "Le marqueur du dossier (id-type)"
     *      }
     *  }
     * )
     * @Rest\Delete("/{marker}")
     */
    public function deleteAction($marker)
    {
        $mediaFolderManager = $this->getMediaFolderManager($marker);
        $this->canManageMediaFolder($mediaFolderManager->getMediaFolderObject());
        $mediaFolderManager->delete($this->get('bns.right_manager')->getUserSessionId());
        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }
}
