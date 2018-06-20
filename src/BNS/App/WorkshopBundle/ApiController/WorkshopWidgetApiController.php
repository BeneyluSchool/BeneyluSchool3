<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetType;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkshopWidgetApiController extends BaseWorkshopApiController
{

    /**
     *
     * @ApiDoc(
     *  section="Atelier - Widgets",
     *  resource = true,
     *  description="Création d'un widget",
     *  statusCodes = {
     *      201 = "Widget créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à l'atelier"
     *   },
     *   parameters= {
     *      {"name"="workshopWidgetGroupId", "dataType" = "integer", "required"=true, "description"="Id du groupe de widget"},
     *      {"name"="position", "dataType" = "string", "required"=true, "description"="Position"},
     *      {"name"="type", "dataType" = "string", "required"=true, "description"="Type de Widget"},
     *      {"name"="mediaId", "dataType" = "integer", "required"=false, "description"="Media associé"},
     *      {"name"="content", "dataType" = "string", "required"=true, "description"="Contenu"},
     *   }
     * )
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup", options={"mapping"={"workshopWidgetGroupId"="id"}})
     * @ParamConverter("media", options={"mapping"={"mediaId"="id"}})
     *
     * @deprecated
     */
    public function postAction(Request $request, WorkshopWidgetGroup $workshopWidgetGroup, Media $media)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);
        $widget = $workshopWidgetGroup->addWidget($request->get('position'), $request->get('type'), $media, $request->get('content'));
        $response = new Response('', Codes::HTTP_CREATED);
        $response->headers->set('Location', $this->generateUrl('workshop_widget_api_get', array(
            'version' => $this->getVersion(),
            'id'      => $widget->getId()
        )));
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Widgets",
     *  resource = true,
     *  description="Détails d'un widget de l'atelier",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id de l'objet"
     *      }
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidget")
     */
    public function getAction($id)
    {
        $workshopWidget = WorkshopWidgetQuery::create()->findPk($id);
        if (!$workshopWidget) {
            throw $this->createNotFoundException();
        }

        $this->canManageWorkshopWidget($workshopWidget);

        return $workshopWidget;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Widgets",
     *  resource = true,
     *  description="Suppression d'un widget",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id de l'objet"
     *      }
     *  }
     * )
     *
     * @Rest\Delete("/{id}")
     * @ParamConverter("workshopWidget")
     */
    public function deleteAction(WorkshopWidget $workshopWidget)
    {
        $this->canManageWorkshopWidget($workshopWidget);

        $workshopWidget->delete();

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Widgets",
     *  resource = true,
     *  description="Met à jour un widget de l'atelier",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données ne sont pas valides",
     *      403 = "Accès interdit",
     *      404 = "Le widget n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function patchAction(WorkshopWidget $workshopWidget)
    {
        $this->canManageWorkshopWidget($workshopWidget);

        return $this->restForm('workshop_widget', $workshopWidget, array(
            // TODO fix this
            'csrf_protection' => false,
        ));
    }

}
