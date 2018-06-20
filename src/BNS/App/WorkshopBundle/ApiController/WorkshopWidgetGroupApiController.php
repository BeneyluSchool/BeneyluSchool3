<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\NotificationBundle\Notification\WorkshopBundle\WorkshopWidgetNewCorrectionNotification;
use BNS\App\NotificationBundle\Notification\WorkshopBundle\WorkshopWidgetWasCorrectedNotification;
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

        $this->get('bns.workshop.widget_group.manager')->updateBreakPage($workshopWidgetGroup->getWorkshopPage()->getWorkshopDocument());

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
    public function patchAction(Request $request, WorkshopWidgetGroup $workshopWidgetGroup)
    {
        $this->canManageWorkshopWidgetGroup($workshopWidgetGroup);
        $ctrl = $this;
        $oldScope = array(
            'pageId' => $workshopWidgetGroup->getPageId(),
            'zone' => $workshopWidgetGroup->getZone(),
            'position' => $workshopWidgetGroup->getPosition(),
        );

        $saveHandler = function ($data, $form) use ($workshopWidgetGroup, $request, $ctrl, $oldScope) {
            if ($oldScope['pageId'] !== $workshopWidgetGroup->getPageId()) {
                // Check if page was on the same document
                $documentIds = WorkshopPageQuery::create()
                    ->filterById([
                        $workshopWidgetGroup->getPageId(),
                        $oldScope['pageId']
                    ])
                    ->select('DocumentId')
                    ->find();

                if (count($documentIds) !== 2 || $documentIds[0] !== $documentIds[1]) {
                    return View::create($form, Codes::HTTP_BAD_REQUEST);
                }

            }
            if ($position = $request->get('position', 0)) {
                $position = $workshopWidgetGroup->getPosition();
                if ($position === $oldScope['position']) {
                    $workshopWidgetGroup->setPosition(-1);
                    $workshopWidgetGroup->setPosition($position);
                }
                $ctrl->get('bns.workshop.widget_group.manager')->applyOrderInPage($workshopWidgetGroup, $oldScope);
            }
            $ctrl->get('bns.workshop.widget_group.manager')->save($workshopWidgetGroup, $ctrl->getUser(), true);

            // update page break
            $this->get('bns.workshop.widget_group.manager')->updateBreakPage($workshopWidgetGroup->getWorkshopPage()->getWorkshopDocument(), true);

            // send notification if widget has a correction
            foreach ($workshopWidgetGroup->getWorkshopWidgets() as $workshopWidget) {
                if ($workshopWidget->hasCorrection()) {
                    $user = $ctrl->getUser();

                    if ($ctrl->get('bns.right_manager')->hasRight('WORKSHOP_CORRECTION_EDIT')) {
                        // get contributor users, directs and from contributor groups
                        $contentManager = $ctrl->get('bns.workshop.content.manager');
                        $content = $workshopWidgetGroup->getWorkshopPage()->getWorkshopDocument()->getWorkshopContent();
                        $contributorUserIds = $contentManager->getContributorUserIds($content);
                        $contributorGroups = $contentManager->getContributorGroups($content);
                        foreach ($contributorGroups as $group) {
                            $groupUserIds = $ctrl->get('bns.group_manager')->setGroup($group)->getUserIdsWithPermission('WORKSHOP_ACCESS');
                            $contributorUserIds = array_merge($contributorUserIds, $groupUserIds);
                        }
                        $contributorUserIds = array_unique($contributorUserIds);

                        // notify all contributors except teachers
                        $notifiedUsers = UserQuery::create()
                            ->filterById(array_unique($contributorUserIds))
                            ->filterByHighRoleId(7, \Criteria::GREATER_THAN)
                            ->find()
                        ;
                        $ctrl->get('notification_manager')->send($notifiedUsers, new WorkshopWidgetNewCorrectionNotification($ctrl->get('service_container'), $workshopWidget->getId()));
                    } else {
                        // notify teacher that made the correction
                        $notifiedUserIds = [$workshopWidget->getCorrection()->getLastCorrectionBy()];
                        $notifiedUsers = UserQuery::create()
                            ->filterById(array_unique($notifiedUserIds))
                            ->find()
                        ;
                        $ctrl->get('notification_manager')->send($notifiedUsers, new WorkshopWidgetWasCorrectedNotification($ctrl->get('service_container'), $workshopWidget->getId(), $user->getId()));
                    }
                    break;
                }
            }
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
     * @Rest\View(serializerGroups={"Default","detail", "document_detail"})
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

        $widgetGroupManager->updateBreakPage($widgetGroup->getWorkshopPage()->getWorkshopDocument(), true);

        return $newWidgetGroup;
    }

}
