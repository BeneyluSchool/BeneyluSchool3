<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use BNS\App\WorkshopBundle\Form\Api\ApiWorkshopWidgetGroupType;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidget;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetExtendedSetting;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroup;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupPeer;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetGroupQuery;
use BNS\App\WorkshopBundle\Model\WorkshopWidgetPeer;
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

class WorkshopWidgetGroupApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  resource = true,
     *  description="Détails d'un groupe de widgets de l'atelier",
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
     * @ParamConverter("workshopWidgetGroup")
     */
    public function getAction(Request $request, WorkshopWidgetGroup $workshopWidgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);
        return $workshopWidgetGroup;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  resource = true,
     *  description="Suppression d'un groupe de widget",
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
     * @ParamConverter("workshopWidgetGroup")
     */
    public function deleteAction(WorkshopWidgetGroup $workshopWidgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);

        $workshopWidgetGroup->delete();

        $this->publish('WorkshopDocument('.$workshopWidgetGroup->getWorkshopPage()->getDocumentId().'):widget_groups:remove', $workshopWidgetGroup);

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  resource = true,
     *  description = "Met à jour un groupe de widgets",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id du groupe de widgets"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Données non valides",
     *      403 = "Pas accès à l'atelier",
     *      404 = "Le groupe de widgets n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @ParamConverter("workshopWidgetGroup")
     */
    public function patchAction(WorkshopWidgetGroup $workshopWidgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);
        $request = $this->getRequest();
        $ctrl = $this;
        $oldScope = array(
            'pageId' => $workshopWidgetGroup->getPageId(),
            'zone' => $workshopWidgetGroup->getZone(),
            'position' => $workshopWidgetGroup->getPosition(),
        );

        $saveHandler = function ($data, $form) use ($workshopWidgetGroup, $request, $ctrl, $oldScope) {
            if ($position = $request->get('position', 0)) {
                $ctrl->get('bns.workshop.widget_group.manager')->applyOrderInPage($workshopWidgetGroup, $oldScope);
            }
            $ctrl->get('bns.workshop.widget_group.manager')->save($workshopWidgetGroup, $ctrl->getUser(), true);
        };
        return $this->restForm(new ApiWorkshopWidgetGroupType(), $workshopWidgetGroup, array(
            // @TODO do this the right way
            'csrf_protection' => false,
        ), null, $saveHandler);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  resource = true,
     *  description="Liste des widgets pour un groupe de widget",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'atelier",
     *      404 = "Le groupe de widgets n'a pas été trouvé"
     *   }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/{id}/widgets")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function listWidgetsAction(ParamFetcherInterface $paramFetcher, WorkshopWidgetGroup $workshopWidgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);

        $query = WorkshopWidgetQuery::create()->filterByWidgetGroupId($workshopWidgetGroup->getId());

        return $this->getPaginator($query, new Route('workshop_widget_api_list', array(
                'version' => $this->getVersion()), true),
            $paramFetcher
        );
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  description="Etablit un verrour sur le groupe de widgets",
     *  resource=true,
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
     * @Rest\Post("/{id}/lock")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup")
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @return Response
     */
    public function addLockAction (WorkshopWidgetGroup $widgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($widgetGroup);

        $user = $this->getUser();
        $lockManager = $this->get('bns.workshop.lock.manager');
        if ($lockManager->canLock($user, $widgetGroup)) {
            $lockManager->lock($user, $widgetGroup, true);

            return new Response('', Codes::HTTP_CREATED);
        } else {
            if ($lockManager->getLock($user, $widgetGroup)) {
                return new Response('', Codes::HTTP_OK);
            }
        }

        return new Response('', Codes::HTTP_FORBIDDEN);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  description="Supprime un verrour sur le groupe de widgets",
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
     * @Rest\Delete("/{id}/lock")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup")
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @return Response
     */
    public function removeLockAction (WorkshopWidgetGroup $widgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($widgetGroup);

        $user = $this->getUser();
        $lockManager = $this->get('bns.workshop.lock.manager');
        $lock = $lockManager->unlock($user, $widgetGroup, true);
        if ($lock) {
            // maybe user has drafts, delete them
            $this->get('bns.workshop.widget_group.manager')->removeDrafts($widgetGroup);

            return new Response('', Codes::HTTP_NO_CONTENT);
        }

        return new Response('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  description="Ajoute un brouillon au groupe de widgets",
     *  resource=true,
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
     * @Rest\Patch("/{id}/drafts")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup")
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @return Response
     */
    public function addDraftAction (WorkshopWidgetGroup $widgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($widgetGroup);

        $request = $this->getRequest();
        $user = $this->getUser();
        $ctrl = $this;

        $saveHandler = function ($data, $form) use ($widgetGroup, $request, $user, $ctrl) {
            $ctrl->get('bns.workshop.widget_group.manager')->setDraft($widgetGroup, $ctrl->getUser());

            return new Response('', Codes::HTTP_CREATED);
        };

        return $this->restForm(new ApiWorkshopWidgetGroupType(), $widgetGroup, array(
            // @TODO do this the right way
            'csrf_protection' => false,
        ), null, $saveHandler);
    }


    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  description="Move a widget group to another page",
     *  resource=true,
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
     * @Rest\Patch("/{id}/move")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup")
     *
     * @return Response
     */
    public function moveAction ($id)
    {
        $widgetGroup = WorkshopWidgetGroupQuery::create()
            ->findOneById($id);

        $this->canManageWorkshopWidgetGroup($widgetGroup);

        $request = $this->getRequest();
        $pageId = $request->get('page_id', 0);

        if (!$pageId) {
            return new Response('', Codes::HTTP_NOT_FOUND);
        }

        $page = WorkshopPageQuery::create()
        ->findOneById($pageId);

        if (!$page) {
            return new Response('', Codes::HTTP_NOT_FOUND);
        }

        $widgetGroup->setPageId($pageId);
        $widgetGroup->setZone(1);
        $widgetGroup->save();

        return new Response('', Codes::HTTP_OK);
    }


    /**
     * @ApiDoc(
     *  section="Atelier - Groupes de widgets",
     *  description="Duplique un groupe de widget",
     *  resource=true,
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
     * @Rest\Post("/{id}/duplicate")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopWidgetGroup")
     *
     * @param WorkshopWidgetGroup $widgetGroup
     * @return WorkshopWidgetGroup
     */
    public function duplicateAction (WorkshopWidgetGroup $widgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($widgetGroup);
        $widgetGroupManager =  $this->get('bns.workshop.widget_group.manager');
        $newWidgetGroup = $widgetGroupManager->duplicate($widgetGroup);
        $newWidgetGroup->save();

        return $newWidgetGroup;
    }

}
