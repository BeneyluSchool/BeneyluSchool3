<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\WorkshopBundle\ApiController\BaseWorkshopApiController;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkshopPageApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Pages",
     *  description="Détails d'une page d'un document de l'atelier",
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
     * @ParamConverter("workshopPage")
     */
    public function getAction(Request $request, WorkshopPage $workshopPage)
    {
        $this->canManageWorkshopPage($workshopPage);
        return $workshopPage;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Pages",
     *  description="Suppression d'une page",
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
     * @ParamConverter("workshopPage")
     */
    public function deleteAction(WorkshopPage $workshopPage)
    {
        $this->canManageWorkshopPage($workshopPage);
        $currentPageCount = WorkshopPageQuery::create()
            ->filterByDocumentId($workshopPage->getDocumentId())
            ->count();

        if ($currentPageCount === 1) {
            throw new \ErrorException('Cannot delete the last page of a document');
        }

        $workshopPage->delete();
        $this->publish('WorkshopDocument('.$workshopPage->getDocumentId().'):pages:remove', $workshopPage);

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Pages",
     *  resource=true,
     *  description="Met à jour une page",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "la page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @param WorkshopPage $workshopPage
     * @param Request $request
     * @return WorkshopPage|mixed
     */
    public function patchAction(WorkshopPage $workshopPage, Request $request)
    {
        $this->canManageWorkshopPage($workshopPage);
        $ctrl = $this;

        return $this->restForm('workshop_page', $workshopPage, array(
            // TODO: do this the right way
            'csrf_protection' => false,
        ), null, function ($data, $form) use ($workshopPage, $request, $ctrl) {
            $positionChanged = false;
            if ($position = (int)$request->get('position', 0)) {
                $oldPosition = $workshopPage->getPosition();
                $workshopPage->moveToRank($position);
                $positionChanged = $oldPosition !== $workshopPage->getPosition();
            }
            $workshopPage->save();

            // build a response only with the submitted data
            if ($request->get('partial')) {
                $publishedData = [
                    'id' => $workshopPage->getId(),
                ];
                foreach ($request->request->all() as $prop => $value) {
                    if ($form->get($prop)) {
                        $publishedData[$prop] = $form->get($prop)->getData();
                    }
                }
            } else {
                $publishedData = $workshopPage;
            }

            $ctrl->publish('WorkshopDocument('.$workshopPage->getDocumentId().'):pages:save', $publishedData);
            if ($positionChanged) {
                $workshopDocument = $workshopPage->getWorkshopDocument();
                $workshopDocument->reload(true);
                $ctrl->publish('WorkshopDocument('.$workshopDocument->getId().'):reorder', $workshopDocument, ['pages_moved']);
            }

            return $workshopPage;
        });
    }

    /**
     * @ApiDoc(
     *  section = "Atelier - Pages",
     *  resource = true,
     *  description="Liste des groupes de widgets pour une page",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Droits insuffisants pour modifier la page",
     *      404 = "La page n'a pas été trouvée"
     *   }
     * )
     * @Rest\Get("/{id}/widget-groups")
     * @Rest\View(serializerGroups={"Default","list"})
     */
    public function listWidgetGroupsAction(WorkshopPage $workshopPage)
    {
        $this->canManageWorkshopPage($workshopPage);

        return $workshopPage->getWorkshopWidgetGroups();
    }

    /**
     * @ApiDoc(
     *  section = "Atelier - Pages",
     *  resource = true,
     *  description = "Création d'un groupe de widgets pour une page",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises ne sont pas valides",
     *      403 = "Droits insuffisants pour modifier la page",
     *      404 = "La page n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Post("/{id}/widget-groups")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function postWidgetGroupAction(WorkshopPage $workshopPage)
    {
        $this->canManageWorkshopPage($workshopPage);
        $router = $this->get('router');
        $ctrl = $this;

        $widgetGroupManager =  $this->get('bns.workshop.widget_group.manager');
        $saveHandler = function ($data, $form) use ($workshopPage, $widgetGroupManager, $router, $ctrl) {
            $widgetGroup = $widgetGroupManager->createFromConfiguration($data, $workshopPage);

            $ctrl->get('bns.workshop.widget_group.manager')->save($widgetGroup, $ctrl->getUser(), true);

            $widgetGroupManager->updateBreakPage($widgetGroup->getWorkshopPage()->getWorkshopDocument());

            $url = $router->generate('workshop_widget_group_api_get', array (
                'version' => '1.0',
                'id' => $widgetGroup->getId(),
            ));
            $response = new Response('', Codes::HTTP_CREATED);
            $response->headers->set('Location', $url);

            return $response;
        };

        return $this->restForm('workshop_widget_configuration', array(), array(
            // TODO: do this the right way
            'csrf_protection' => false,
        ), null, $saveHandler);
    }


    /**
     * @ApiDoc(
     *  section="Atelier - Pages",
     *  description="Détails d'une page d'un document en fonction de sa position",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id de l'objet"
     *      },
     *     {
     *          "name"="position",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="La position de l'objet"
     *     }
     *  }
     * )
     *
     * @Rest\Get("/{id}/first/{position}")
     * @Rest\View(serializerGroups={"Default","detail"})
     * @ParamConverter("workshopPage")
     */
    public function getFirstAction($id, $position)
    {
        $rightManager = $this->get('bns.right_manager');
        $first = WorkshopPageQuery::create()
            ->filterByDocumentId($id)
            ->filterByPosition($position)
            ->findOne();
        if (!$first) {
            throw new NotFoundHttpException();
        }
        if ($rightManager->hasRight('COMPETITION_ACCESS')) {
            if (MediaManager::STATUS_QUESTIONNAIRE_COMPETITION === $first->getWorkshopDocument()->getWorkshopContent()->getMedia()->getStatusDeletion())
            return $first;
        }
        $this->canManageWorkshopPage($first);
        return $first;
    }

}
