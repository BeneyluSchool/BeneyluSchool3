<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class WorkshopThemeApiController
 *
 * @package BNS\App\WorkshopBundle\ApiController
 */
class WorkshopThemeApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Thèmes",
     *  resource=true,
     *  description="Liste des thèmes disponibles"
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function listAction(Request $request)
    {
        $manager = $this->get('bns.workshop.theme.manager');

        return $manager->getList();
    }

}
