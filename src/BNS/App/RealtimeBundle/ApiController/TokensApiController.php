<?php

namespace BNS\App\RealtimeBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * Class TokensApiController
 */
class TokensApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section = "Tokens",
     *  resource=true,
     *  description="Récupère un token d'authentification pour la ressource donnée"
     * )
     *
     * @Rest\Get("/{resource}")
     * @Rest\View
     *
     * @param string $resource
     * @return string
     */
    public function getAction($resource = null)
    {
        // TODO: check access to resource

        return array(
            'token' => $this->get('bns.realtime.token_manager')->getToken($resource),
        );
    }

}
