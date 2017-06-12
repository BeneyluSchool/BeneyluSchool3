<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\NotificationBundle\Notification\WorkshopBundle\WorkshopNewContributorNotification;
use BNS\App\NotificationBundle\Notification\WorkshopBundle\WorkshopNewDocumentNotification;
use BNS\App\WorkshopBundle\Model\WorkshopContentInterface;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkshopDocumentApiController extends BaseWorkshopApiController
{
    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Création d'un document de l'atelier - et donc de la ressource associée forcément",
     *  statusCodes = {
     *      201 = "Document créé",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à l'atelier"
     *   },
     *   parameters= {
     *      {"name"="label", "dataType" = "string", "required"=true, "description"="Nom du document"},
     *   }
     * )
     *
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function postAction(Request $request)
    {
        $this->checkWorkshopAccess();

        $questionnaire = $request->request->get('questionnaire');

        $workshopDocument = $this->get('bns.workshop.document.manager')->create($questionnaire);

        $workshopDocument->save();
        $this->get('bns.workshop.content.manager')->setContributorUserIds($workshopDocument->getWorkshopContent(), array($this->getUser()->getId()));
        $workshopDocument->getWorkshopContent()->save();

        // notif to teachers when child creates a document
        $userManager = $this->get('bns.user_manager');
        if ($userManager->isChild()) {
            $teachers = array();
            foreach ($userManager->getGroupsUserBelong('CLASSROOM') as $classroom) {
                foreach($this->get('bns.group_manager')->setGroup($classroom)->getUsersByRoleUniqueName('TEACHER', true) as $teacher) {
                    $teachers[] = $teacher;
                }
            }
            if (count($teachers) > 0) {
                $this->get('notification_manager')->send($teachers, new WorkshopNewDocumentNotification($this->container, $workshopDocument->getId()));
            }
        }

        $response = new Response('', Codes::HTTP_CREATED);
        $response->headers->set('Location', $this->generateUrl('workshop_document_api_get', array(
            'version' => $this->getVersion(),
            'id'      => $workshopDocument->getId()
        )));

        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Liste des documents de l'utilisateur en cours",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'atelier"
     *   }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","contributors_list"})
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return \Hateoas\Representation\PaginatedRepresentation
     */
    public function listAction(ParamFetcherInterface $paramFetcher)
    {
        $this->checkWorkshopAccess();
        $user = $this->getUser();

        // get the user ids of all managed users that can be authors, plus the current user
        $potentialAuthorIds = $this->getManagedAuthorIds();
        if (!in_array($user->getId(), $potentialAuthorIds)) {
            $potentialAuthorIds[] = $user->getId();
        }

        $query = WorkshopDocumentQuery::create()
            ->useWorkshopContentQuery()
                ->lastUpdatedFirst()
                ->useMediaQuery()
                    ->filterByStatusDeletion(1)
                ->endUse()

                ->filterByAuthorId($potentialAuthorIds)
                ->_or()
                ->useWorkshopContentContributorQuery(null, \Criteria::LEFT_JOIN)
                     ->filterByUser($user)
                ->endUse()
                ->_or()
                ->useWorkshopContentGroupContributorQuery(null, \Criteria::LEFT_JOIN)
                    ->filterByGroup($user->getGroups())
                ->endUse()

            ->endUse()
            ->groupById()
        ;

        return $this->getPaginator($query, new Route('workshop_document_api_list', array(
                'version' => $this->getVersion()), true),
            $paramFetcher
        );
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Détails d'un document de l'atelier",
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
     * @ParamConverter("workshopDocument", options={"mapping"={"id"="resource_id"}})
     *
     * @param WorkshopDocument $workshopDocument
     * @param Request $request
     * @return WorkshopDocument
     */
    public function getAction(WorkshopDocument $workshopDocument, Request $request)
    {
        if ($request->query->has('objectType') && $request->query->has('objectId')) {
            $this->canViewWorkshopDocumentJoined($workshopDocument, $request->get('objectType'), (int) $request->get('objectId'));
        } else {
            $this->canViewWorkshopDocument($workshopDocument);
        }

        return $workshopDocument;
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Suppression d'un document",
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
     * @ParamConverter("workshopDocument", options={"mapping"={"id"="resource_id"}})
     *
     * @param WorkshopDocument $workshopDocument
     * @return View
     */
    public function deleteAction(WorkshopDocument $workshopDocument)
    {
        $this->canManageWorkshopDocument($workshopDocument);
        $workshopDocument->getMedia()->delete();

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Met à jour un document",
     *  requirements={
     *      {
     *          "name"="id",
     *          "dataType"="integer",
     *          "requirement"="\d+",
     *          "description"="L'id de l'objet"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Données invalide",
     *      404 = "Si le document n'a pas été trouvé"
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @ParamConverter("workshopDocument", options={"mapping"={"id"="resource_id"}})
     *
     * @param WorkshopDocument $workshopDocument
     * @return Response
     */
    public function patchAction(WorkshopDocument $workshopDocument)
    {
        $this->canManageWorkshopDocument($workshopDocument);

        $contentManager = $this->get('bns.workshop.content.manager');
        $request = $this->getRequest();
        $ctrl = $this;

        return $this->restForm('workshop_document', $workshopDocument, array(
            'csrf_protection' => false,
        ), null, function ($data, $form) use ($request, $contentManager, $workshopDocument, $ctrl) {

            // update lock, if given and user has right
            $doLock = $request->get('is_locked');
            if ((true === $doLock || false === $doLock) && $ctrl->get('bns.user_manager')->hasRightSomeWhere('WORKSHOP_ACTIVATION')) {
                if ($doLock) {
                    $workshopDocument->setStatus(WorkshopDocument::STATUS_LOCKED);
                } else {
                    $workshopDocument->setStatus(WorkshopDocument::STATUS_EDITABLE);
                }
            }

            $attempts = $request->get('attempts_number');
            if ($attempts && $ctrl->get('bns.user_manager')->hasRightSomeWhere('SCHOOL_COMPETITION_MANAGE')) {
                $workshopDocument->setAttemptsNumber($attempts);
            }
            // update user ids, if given
            $userIds = $request->get('user_ids', false);
            if (is_array($userIds)) {
                $userIds = $ctrl->checkContributorUserIds($workshopDocument, $userIds);
                $stats = $contentManager->setContributorUserIds($workshopDocument->getWorkshopContent(), $userIds);

                // notify newly-added contributors
                if (count($stats['added'])) {
                    $newContributors = UserQuery::create()->findPks($stats['added']);
                    $ctrl->get('notification_manager')->send($newContributors, new WorkshopNewContributorNotification($ctrl->get('service_container'), $workshopDocument->getId()));
                }
            }

            // update group ids, if given
            $groupIds = $request->get('group_ids', false);
            if (is_array($groupIds)) {
                $groupIds = $ctrl->checkContributorGroupIds($workshopDocument, $groupIds);
                $contentManager->setContributorGroupIds($workshopDocument->getWorkshopContent(), $groupIds);
            }

            // force save of related parent object
            $workshopDocument->save();
            $workshopDocument->getWorkshopContent()->save();

            $ctrl->publish('WorkshopDocument('.$workshopDocument->getId().'):save', $workshopDocument, array('Default', 'contributors'));

            return $workshopDocument;
        });
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Liste des pages d'un document",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'atelier",
     *      404 = "Le document n'a pas été trouvé"
     *   }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/{id}/pages")
     * @Rest\View(serializerGroups={"Default","list"})
     * @ParamConverter("workshopDocument", options={"mapping"={"id"="resource_id"}})
     *
     * @param WorkshopDocument $workshopDocument
     * @param ParamFetcherInterface $paramFetcher
     * @return \Hateoas\Representation\PaginatedRepresentation
     */
    public function listPagesAction(WorkshopDocument $workshopDocument, ParamFetcherInterface $paramFetcher)
    {
        $this->canManageWorkshopDocument($workshopDocument);
        $query = WorkshopPageQuery::create()
            ->filterByWorkshopDocument($workshopDocument)
        ;

        return $this->getPaginator(
            $query,
            new Route('workshop_document_api_list_pages', array(
                'version' => $this->getVersion(),
                'id' => $workshopDocument->getId(),
            ), true),
            $paramFetcher
        );
    }

    /**
     * @ApiDoc(
     *  section="Atelier - Documents",
     *  resource=true,
     *  description="Création d'une page pour un document",
     *  statusCodes = {
     *      201 = "Page créée",
     *      400 = "Erreur dans le traitement",
     *      403 = "Pas accès à l'atelier",
     *      404 = "Le document n'a pas été trouvé"
     *   }
     * )
     *
     * @Rest\Post("/{id}/pages")
     * @ParamConverter("workshopDocument", options={"mapping"={"id"="resource_id"}})
     *
     * @param WorkshopDocument $workshopDocument
     * @return Response
     */
    public function postPageAction(WorkshopDocument $workshopDocument, Request $request)
    {
        $position = $request->get('position', false);
        $layout = $request->get('layout', false);
        $this->canManageWorkshopDocument($workshopDocument);
        $newPage = new WorkshopPage();
        if ($position) {
            $newPage->setPosition($position);
        }
        if ($layout) {
            $newPage->setLayoutCode($layout);
        }
        $workshopDocument->addWorkshopPage($newPage);
        $workshopDocument->save();

        $this->publish('WorkshopDocument('.$workshopDocument->getId().'):pages:save', $newPage);

        return new Response('', Codes::HTTP_CREATED, array(
            'Location' => $this->generateUrl('workshop_page_api_get', array(
                'version' => $this->getVersion(),
                'id' => $newPage->getId(),
            )),
        ));
    }

    public function checkContributorUserIds(WorkshopContentInterface $workshopDocument, $ids)
    {
        $previousIds = $this->get('bns.workshop.content.manager')->getContributorUserIds($workshopDocument->getWorkshopContent());
        $currentLocks = $this->get('bns.workshop.lock.manager')->getUserLocks($previousIds, $workshopDocument->getId());

        $validIds = parent::checkContributorUserIds($workshopDocument, $ids);

        // user with a lock cannot be removed
        if ($currentLocks->count()) {
            foreach ($currentLocks as $lock) {
                if (!in_array($lock->getUserId(), $validIds)) {
                    $validIds[] = $lock->getUserId();
                }
            }
        }

        return $validIds;
    }

}
