<?php

namespace BNS\App\ResourceBundle\ApiController;

use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\ResourceBundle\Model\Resource;

class BaseResourceApiController extends BaseApiController
{

    /**
     * Vérifie l'accès à l'atelier
     */
    protected function checkResourceAccess()
    {
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasRightSomeWhere('RESOURCE_ACCESS'));
    }

    /////////////////   METHODES de vérification d'accès en management   \\\\\\\\\\\\\\\\\\\\\\
    /**
     * Toutes ces méthodes cascadent les unes avec les autres
     * pour arriver sur la vérification au niveau de la ressource
     */

    protected function canReadResource(Resource $resource)
    {
        return $this->get('bns.resource_right_manager')->canReadResource($resource);
    }

    protected function canManageResource(Resource $resource)
    {
        $this->checkResourceAccess();
        return $this->get('bns.resource_right_manager')->canManageResource($resource);
    }

}
