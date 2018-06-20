<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSettingQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkshopQuestionnaireApiController extends BaseWorkshopApiController
{
    /**
     * @ApiDoc(
     *  section="Atelier - Questionnaire",
     *  resource=true,
     *  description="Vérifie si les réponses sont bonnes"
     * ),
     * @ParamConverter("workshopWidget")
     * @Rest\Post("/{id}/{type}/verify")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyAction(Request $request, $id, $type)
    {
        $widget = WorkshopWidgetQuery::create()
            ->findOneById($id);

        if (!$widget) {
            return View::create('', Codes::HTTP_NOT_FOUND);
        }
        $questionnaireManager = $this->get('bns.workshop.questionnaire.manager');
        $response = $questionnaireManager->verifyAnswer($request->get('data'), $widget, $type, $request->get('show_solution'));

        return new JsonResponse($response);
    }
}
