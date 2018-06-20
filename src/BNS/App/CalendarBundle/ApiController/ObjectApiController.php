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
use BNS\App\CoreBundle\Model\AgendaObject;
use BNS\App\CoreBundle\Model\AgendaObjectQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Symfony\Component\HttpFoundation\Request;

class ObjectApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Calendrier- objet",
     *  resource=true,
     *  description="Liste les objets",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View()
     *
     * @RightsSomeWhere("CALENDAR_SDET_RESERVATION")
     *
     * @return array
     */
    public function getListAction()
    {

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_SDET_RESERVATION');
        $objects = AgendaObjectQuery::create()->filterByGroupId($groupIds, \Criteria::IN)->orderByGroupId()->find();
        return $objects;
    }


    /**
     * @ApiDoc(
     *  section="Calendrier- objet",
     *  resource=true,
     *  description="Liste les groupes ou l'utilisateur peut ajouter des objets",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Invalid request",
     *  }
     * )
     * @Rest\Get("/groups")
     * @Rest\View(serializerGroups={"Default", "agenda_detail"})
     *
     * @RightsSomeWhere("CALENDAR_SDET_RESERVATION")
     *
     * @return array
     */
    public function getGroupListAction()
    {

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('CALENDAR_SDET_RESERVATION');

        return GroupQuery::create()->filterById($groupIds)->find();
    }


    /**
     * @ApiDoc(
     *  section="Calendrier- objet",
     *  description="Création d'une objet",
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
     * @RightsSomeWhere("CALENDAR_SDET_RESERVATION")
     *
     * @param Request $request
     */
    public function postAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CALENDAR_SDET_RESERVATION', $request->get('group_id'))) {
            throw $this->createAccessDeniedException();
        }
        $object = new AgendaObject();
        $object->setColorClass(AgendaObject::$colors[rand(0,12)]);
        $object->setGroupId($request->get('group_id'))->setTitle($request->get('title'))->save();
        return $this->view($object, Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section="Calendrier- objet",
     *  description="mdofication d'une objet",
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
     * @RightsSomeWhere("CALENDAR_SDET_RESERVATION")
     *
     * @param Request $request
     */
    public function patchAction($id, Request $request)
    {
        $object = AgendaObjectQuery::create()->findPk($id);
        if (!$object) {
            throw $this->createNotFoundException();
        }
        $rightManager = $this->get('bns.right_manager');
        if (!$rightManager->hasRight('CALENDAR_SDET_RESERVATION', $object->getGroupId())) {
            throw $this->createAccessDeniedException();
        }
        $object->setTitle($request->get('title'))->save();
        return $object;
    }



    /**
     * @ApiDoc(
     *  section="Calendrier- objet",
     *  description="Suppression d'une objet",
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
     * @RightsSomeWhere("CALENDAR_SDET_RESERVATION")
     *
     * @param int $id
     */
    public function deleteAction($id)
    {
        $object = AgendaObjectQuery::create()->findPk($id);
        if (!$object) {
            throw $this->createNotFoundException();
        }

        $rm = $this->get('bns.right_manager');
        if (!$rm->hasRight('CALENDAR_SDET_RESERVATION', $object->getGroupId())) {
            throw  $this->createAccessDeniedException();
        }
        $object->delete();
        return $this->view('', Codes::HTTP_OK);
    }
}