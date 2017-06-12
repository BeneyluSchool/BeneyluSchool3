<?php

namespace BNS\App\WorkshopBundle\ApiController;

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

        $query = WorkshopContentQuery::create()
            ->useMediaQuery()
                ->filterByStatusDeletion(1)
            ->endUse()
            ->lastUpdatedFirst()
            ->filterByAuthorId($potentialAuthorIds)
            ->_or()
            ->useWorkshopContentContributorQuery(null, \Criteria::LEFT_JOIN)
                 ->filterByUser($user)
            ->endUse()
            ->_or()
            ->useWorkshopContentGroupContributorQuery(null, \Criteria::LEFT_JOIN)
                ->filterByGroup($user->getGroups())
            ->endUse()
            ->groupById()
        ;

        return $this->getPaginator($query, new Route('workshop_content_api_list', array(
            'version' => $this->getVersion()
        ), true), $paramFetcher);
    }

}
