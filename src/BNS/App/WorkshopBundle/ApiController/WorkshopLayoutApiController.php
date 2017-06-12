<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

class WorkshopLayoutApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Layouts",
     *  resource=true,
     *  description="Liste des layouts disponibles"
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function listAction(Request $request)
    {
        $manager = $this->get('bns.workshop.layout.manager');

        return $manager->getList();
    }

}
