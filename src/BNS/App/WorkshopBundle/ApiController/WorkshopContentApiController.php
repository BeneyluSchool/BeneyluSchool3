<?php

namespace BNS\App\WorkshopBundle\ApiController;

use BNS\App\WorkshopBundle\Model\WorkshopContentContributorPeer;
use BNS\App\WorkshopBundle\Model\WorkshopContentContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorPeer;
use BNS\App\WorkshopBundle\Model\WorkshopContentGroupContributorQuery;
use BNS\App\WorkshopBundle\Model\WorkshopContentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopContentQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class WorkshopContentApiController extends BaseWorkshopApiController
{

    /**
     * @ApiDoc(
     *  section="Atelier - Contenus",
     *  resource=true,
     *  description="Liste des contenus de l'atelier, de tout type",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à l'atelier"
     *   }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("")
     * @Rest\View(serializerGroups={"Default","list"})
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

        $contentAuthorIds = WorkshopContentQuery::create()
            ->filterByAuthorId($potentialAuthorIds)
            ->select('Id')
            ->find()->toArray();

        $contentContributorIds = WorkshopContentContributorQuery::create()
            ->filterByUser($user)
            ->select(WorkshopContentContributorPeer::CONTENT_ID)
            ->find()->toArray();
        $contentGroupContributorIds = WorkshopContentGroupContributorQuery::create()
            ->filterByGroup($user->getGroups())
            ->select(WorkshopContentGroupContributorPeer::CONTENT_ID)
            ->find()->toArray();
        $contentIds = array_unique(array_merge($contentAuthorIds, $contentContributorIds, $contentGroupContributorIds));
        $query = WorkshopContentQuery::create()
            ->filterByType(WorkshopContentPeer::getValueSet(WorkshopContentPeer::TYPE ))
            ->useMediaQuery()
                ->filterByStatusDeletion(1)
            ->endUse()
            ->lastUpdatedFirst()
            ->filterById($contentIds)
            ->groupById()
        ;
        $this->get('stat.workshop')->visit();
        return $this->getPaginator($query, new Route('workshop_content_api_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

}
