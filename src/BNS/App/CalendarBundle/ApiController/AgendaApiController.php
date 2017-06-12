<?php

namespace BNS\App\CalendarBundle\ApiController;

use BNS\App\CalendarBundle\Form\Type\AgendaApiType;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Agenda;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AgendaApiController
 *
 * @package BNS\App\CalendarBundle\ApiController
 */
class AgendaApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="Liste les agendas",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default", "agenda_list","with_manageable"})
     *
     * @RightsSomeWhere("CALENDAR_ACCESS")
     *
     * @return array
     */
    public function getListAction()
    {
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds($visibleGroupIds);

        return $agendas;
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="Modifie un agendas",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      404 = "Not found",
     *  }
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param Agenda $agenda
     * @return Response
     */
    public function patchAction(Agenda $agenda)
    {
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds($visibleGroupIds);

        if (!$agendas->contains($agenda)) {
            throw $this->createNotFoundException();
        }

        if (!$this->get('bns.right_manager')->hasRight('CALENDAR_ACCESS_BACK', $agenda->getGroupId())){
            throw $this->createAccessDeniedException();
        }

        return $this->restForm(new AgendaApiType(), $agenda, [
            'csrf_protection' => false, // TODO
        ]);
    }

}
