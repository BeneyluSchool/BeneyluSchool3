<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 08/02/2018
 * Time: 15:59
 */

namespace BNS\App\CalendarBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\CoreBundle\Model\Agenda;
use BNS\App\CoreBundle\Model\AgendaPeer;
use BNS\App\CoreBundle\Model\AgendaQuery;
use BNS\App\CoreBundle\Model\AgendaSubject;
use BNS\App\CoreBundle\Model\AgendaSubjectQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Symfony\Component\HttpFoundation\Request;

class SubjectApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Calendrier- discipline",
     *  resource=true,
     *  description="Liste les disciplines",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_SDET_DISCIPLINE")
     *
     * @return array
     */
    public function getListAction()
    {
        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_SDET_DISCIPLINE');
        $subjects = AgendaSubjectQuery::create()->filterByGroupId($groupIds, \Criteria::IN)->orderByGroupId()->find();
        return $subjects;
    }


    /**
     * @ApiDoc(
     *  section="Calendrier- discipline",
     *  resource=true,
     *  description="Liste les groupes ou l'utilisateur peut ajouter des disciplines",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("/groups")
     * @Rest\View(serializerGroups={"Default", "agenda_detail"})
     *
     * @RightsSomeWhere("CALENDAR_SDET_DISCIPLINE")
     *
     * @return array
     */
    public function getGroupListAction()
    {

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_SDET_DISCIPLINE');

        return GroupQuery::create()->filterById($groupIds)->find();
    }


    /**
     * @ApiDoc(
     *  section="Calendrier- discipline",
     *  description="Création d'une discipline",
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
     * @RightsSomeWhere("CALENDAR_SDET_DISCIPLINE")
     *
     * @param Request $request
     */
    public function postAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CALENDAR_SDET_DISCIPLINE', $request->get('group_id'))) {
            throw $this->createAccessDeniedException();
        }
        $subject = new AgendaSubject();
        $color = AgendaQuery::create()->filterByGroupId($request->get('group_id'))->select(AgendaPeer::COLOR_CLASS)->findOne();
        $subject->setColorClass($color);
        $subject->setGroupId($request->get('group_id'))->setTitle($request->get('title'))->save();
        return $this->view($subject, Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section="Calendrier- discipline",
     *  description="mdofication d'une discipline",
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
     * @RightsSomeWhere("CALENDAR_SDET_DISCIPLINE")
     *
     * @param Request $request
     */
    public function patchAction($id, Request $request)
    {
        $subject = AgendaSubjectQuery::create()->findPk($id);
        if (!$subject) {
            throw $this->createNotFoundException();
        }
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CALENDAR_SDET_DISCIPLINE', $subject->getGroupId())) {
            throw $this->createAccessDeniedException();
        }
        $subject->setTitle($request->get('title'))->save();
        return $subject;
    }



    /**
     * @ApiDoc(
     *  section="Calendrier- discipline",
     *  description="Suppression d'une discipline",
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
     * @RightsSomeWhere("CALENDAR_SDET_DISCIPLINE")
     *
     * @param int $id
     */
    public function deleteAction($id)
    {
        $subject = AgendaSubjectQuery::create()->findPk($id);
        if (!$subject) {
            throw $this->createNotFoundException();
        }

        $rm = $this->get('bns.right_manager');
        if (!$rm->hasRight('CALENDAR_SDET_DISCIPLINE', $subject->getGroupId())) {
            throw  $this->createAccessDeniedException();
        }
        $subject->delete();
        return $this->view('', Codes::HTTP_OK);
    }
}
