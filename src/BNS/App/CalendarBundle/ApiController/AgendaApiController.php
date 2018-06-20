<?php

namespace BNS\App\CalendarBundle\ApiController;

use BNS\App\CalendarBundle\Form\Type\AgendaApiType;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\AgendaUserPeer;
use BNS\App\CoreBundle\Model\AgendaUserQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
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
        if ($this->hasFeature('calendar_sdet_personnal')) {
            $this->get('bns.calendar_manager')->findPersonalAgendaOrCreate($this->getUser());
        }
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($visibleGroupIds, $this->getCurrentUserId());
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
     * @RightsSomeWhere("CALENDAR_ADMINISTRATION")
     *
     * @param Agenda $agenda
     * @return Response
     */
    public function patchAction(Agenda $agenda)
    {
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($visibleGroupIds);

        if (!$agendas->contains($agenda)) {
            throw $this->createNotFoundException();
        }

        if (!$this->get('bns.right_manager')->hasRight('CALENDAR_ACCESS_BACK', $agenda->getGroupId())){
            throw $this->createAccessDeniedException();
        }

        // only use for this patch API for now is to change agenda color
        if (!$this->hasFeature('calendar_color')) {
            throw $this->createAccessDeniedException();
        }

        return $this->restForm(new AgendaApiType(), $agenda, [
            'csrf_protection' => false, // TODO
        ]);
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="Ajoute/enléve un droit sur l'agenda",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      404 = "Not found",
     *  }
     * )
     * @Rest\Patch("/editors/{id}")
     * @Rest\RequestParam(name="userIds", description="array of editors to give right on agenda", array=true)
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param Agenda $agenda
     * @return Agenda
     */
    public function patchEditorsAction(Agenda $agenda, ParamFetcherInterface $paramFetcher)
    {
        if (!$this->hasFeature('calendar_sdet_delegate')) {
            throw $this->createAccessDeniedException();
        }
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ADMINISTRATION');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($visibleGroupIds, $this->getCurrentUserId());

        if (!$agendas->contains($agenda)) {
            throw $this->createNotFoundException();
        }

        $newUsers = $paramFetcher->get('userIds');
        $oldUsers = AgendaUserQuery::create()->filterByAgendaId($agenda->getId())->select(AgendaUserPeer::USER_ID)->find()->toArray();
        $usersToAdd = array_diff($newUsers, $oldUsers);
        $usersToDelete = array_diff($oldUsers, $newUsers);
        foreach (UserQuery::create()->filterById($usersToAdd)->find() as $userToAdd) {
            $agenda->addeditor($userToAdd);
        }
        $agenda->save();
        AgendaUserQuery::create()->filterByUserId($usersToDelete)->filterByAgendaId($agenda->getId())->delete();
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="export l'agenda sous format ical",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      404 = "Not found",
     *  }
     * )
     * @Rest\Get("/export/{id}")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param Agenda $agenda
     * @return Agenda
     */
    public function exportAgendaAction(Agenda $agenda)
    {
        if (!$this->hasFeature('calendar_sdet_export')) {
            throw $this->createAccessDeniedException();
        }
        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIdsAndUser($visibleGroupIds);

        if (!$agendas->contains($agenda)) {
            throw $this->createNotFoundException();
        }

        return $this->getParameter('application_base_url'). '/calendrier/' . 'export/' . $agenda->getId() . '/' .md5('calendar-'. $agenda->getId());


    }

}
