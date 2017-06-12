<?php

namespace BNS\App\ResourceBundle\ApiController;

use BNS\App\ResourceBundle\Model\Resource;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class ResourceApiController
 *
 * @package BNS\App\ResourceBundle\ApiController
 */
class ResourceApiController extends BaseResourceApiController
{

    /**
     * @ApiDoc(
     *  section = "Médiathèque - Ressources",
     *  resource = true,
     *  description = "Détails d'une ressource de la médiathèque",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'id de la ressource"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la ressource",
     *      404 = "La ressource n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function getAction(Resource $resource)
    {
        if (!$this->canReadResource($resource)) {
            throw new AccessDeniedHttpException('Forbidden Action');
        }

        return $resource;
    }

}
