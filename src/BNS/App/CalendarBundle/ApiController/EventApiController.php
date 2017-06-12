<?php

namespace BNS\App\CalendarBundle\ApiController;

use BNS\App\CalendarBundle\Form\Model\CalendarEventFormModel;
use BNS\App\CalendarBundle\Form\Type\CalendarEventType;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\AgendaEvent;
use BNS\App\CoreBundle\Model\AgendaQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EventApiController
 *
 * @package BNS\App\CalendarBundle\ApiController
 */
class EventApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="Liste les événements",
     *  requirements = {
     *      {
     *          "name" = "start",
     *          "dataType" = "date",
     *          "description" = "Date à partir de laquelle récupérer les événements"
     *      }, {
     *          "name" = "end",
     *          "dataType" = "date",
     *          "description" = "Date jusqu'à laquelle récupérer les événements"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default", "agenda_detail"})
     *
     * @RightsSomeWhere("CALENDAR_ACCESS")
     *
     * @param Request $request
     * @return array
     */
    public function getListAction(Request $request)
    {
        // TODO: is admin?

        // add a buffer day before and after, because vcalendar does not handle timezone differences in hours
        $startDate = strtotime($request->get('start', '')) - 24 * 3600;
        $endDate = strtotime($request->get('end', '')) + 24 * 3600;
        $isEditable = ( 1 == $request->get('editing', 0)) && $this->get('bns.right_manager')->hasRightSomeWhere('CALENDAR_ACCESS_BACK');

        $visibleGroupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_ACCESS');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds($visibleGroupIds);

        $events = $this->get('bns.calendar_manager')->selectEventsByDates($startDate, $endDate, $agendas, $isEditable, true);

        return $events;
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  resource=true,
     *  description="Détails d'un événement",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "description" = "ID de l'événement"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default", "agenda_event_detail", "agenda_detail", "media_basic"})
     *
     * @RightsSomeWhere("CALENDAR_ACCESS")
     *
     * @param $id
     * @return AgendaEvent
     */
    public function getAction($id)
    {
        $event = $this->get('bns.calendar_manager')->getEventById($id);

        $canSee = false;
        if (date('m') == 12) {
            $agendaXmas = AgendaQuery::create()->findOneByGroupId(1);
            if ($agendaXmas) {
                $agendaXmasId = $agendaXmas->getId();
                if ($event->getAgendaId() == $agendaXmasId) {
                    $canSee = true;
                }
            }
        }

        if (!($this->get('bns.right_manager')->hasRight('CALENDAR_ACCESS',$event->getAgenda()->getGroupId()) || $canSee)) {
            return $this->view('', Codes::HTTP_FORBIDDEN);
        }

        return $event;
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  description="Création d'un événement",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Post("")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param Request $request
     * @return Response
     */
    public function postAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $mediaManager = $this->get('bns.media.manager');
        $statsCalendar = $this->get("stat.calendar");
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds(
            $rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK')
        );

        $agendaEvent = new AgendaEvent();
        $currentGroupAgenda = AgendaQuery::create()->findOneByGroupId($rightManager->getCurrentGroupId());
        $agendaEvent->setAgenda($currentGroupAgenda);
        $agendaEvent->setDateStart(date('H:i'));
        $agendaEvent->setDateEnd(date('H:i'));

        return $this->restForm(
            new CalendarEventType($agendas, $rightManager->getLocale()),
            new CalendarEventFormModel($agendaEvent),
            [
                'csrf_protection' => false, // TODO
            ],
            null,
            function ($data, $form) use ($request, $mediaManager, $statsCalendar) {
                /** @var CalendarEventFormModel $data */

                $mediaManager->bindAttachments($data->getAgendaEvent(), $request);
                $data->save();
                $mediaManager->saveAttachments($data->getAgendaEvent(), $request);

                $statsCalendar->newEvent();

                return $data;
            }
        );
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  description="Création d'un événement",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param int $id
     * @param Request $request
     * @return Response
     */
    public function patchAction($id, Request $request)
    {
        $agendaEvent = $this->get('bns.calendar_manager')->getEventById($id);
        if (!$this->get('bns.right_manager')->hasRight('CALENDAR_ACCESS_BACK',$agendaEvent->getAgenda()->getGroupId())) {
            return $this->view('', Codes::HTTP_FORBIDDEN);
        }

        $rightManager = $this->get('bns.right_manager');
        $mediaManager = $this->get('bns.media.manager');
        $agendas = $this->get('bns.calendar_manager')->getAgendasFromGroupIds(
            $rightManager->getGroupIdsWherePermission('CALENDAR_ACCESS_BACK')
        );

        $data = json_decode($request->getContent(), true);
        $agendaEvent->setAgendaId($data['agendaId']);

        return $this->restForm(
            new CalendarEventType($agendas, $rightManager->getLocale()),
            new CalendarEventFormModel($agendaEvent),
            [
                'csrf_protection' => false, // TODO
            ],
            null,
            function ($data, $form) use ($request, $mediaManager) {
                /** @var CalendarEventFormModel $data */
                $mediaManager->bindAttachments($data->getAgendaEvent(), $request);
                $data->save();
                $mediaManager->saveAttachments($data->getAgendaEvent(), $request);

                return $data;
            }
        );
    }

    /**
     * @ApiDoc(
     *  section="Calendrier",
     *  description="Suppression d'un événement",
     *  requirements = {},
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *      403 = "No access",
     *  }
     * )
     * @Rest\Delete("/{id}")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_ACCESS_BACK")
     *
     * @param int $id
     * @return Response
     */
    public function deleteAction($id)
    {
        $calendarManager = $this->get('bns.calendar_manager');
        $event = $calendarManager->getEventById($id);
        $rm = $this->get('bns.right_manager');
        $rm->forbidIf(!$rm->hasRight('CALENDAR_ACCESS_BACK',$event->getAgenda()->getGroupId()));

        $event->delete();

        return $this->view('', Codes::HTTP_OK);
    }

}
