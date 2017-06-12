<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class WorkshopWidgetConfigurationApiController
 *
 * @package BNS\App\WorkshopBundle\ApiController
 */
class WorkshopWidgetConfigurationApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Configurations de widgets",
     *  resource=true,
     *  description="Liste des configurations de widgets disponible"
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function listAction(Request $request)
    {
        $manager = $this->get('bns.workshop.widget_configuration.manager');
        $rightManager = $this->get('bns.right_manager');
        $list = $manager->getList();

        return array_filter($list, function ($widget) use ($rightManager){
            if (!isset($widget['permission'])) {
                return true;
            }
            return $rightManager->hasRight($widget['permission']);

        });
    }

}
