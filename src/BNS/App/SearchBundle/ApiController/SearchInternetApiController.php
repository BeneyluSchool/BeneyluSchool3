<?php

namespace BNS\App\SearchBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;

use BNS\App\CoreBundle\Model\User;
use BNS\App\SearchBundle\Model\SearchHistoric;
use BNS\App\SearchBundle\Model\SearchHistoricQuery;
use BNS\App\SearchBundle\Model\SearchInternet;
use BNS\App\SearchBundle\Model\SearchInternetQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SearchInternetApiController
 *
 * @package BNS\App\UserBundle\ApiController
 */
class SearchInternetApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Search Internet",
     *  resource = true,
     *  description="Get a group's search",
     * )
     *
     * @Rest\Get("/search/logs")
     * @Rest\View(serializerGroups={"Default","media_search"})
     */
    public function getSearchInternetAction()
    {
        $this->get('stat.search')->visit();

        return array(
            'lastSearchs' => SearchInternetQuery::create()
                ->joinUser()
                ->filterByUserId($this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueNameIds('PUPIL'))
                ->orderByCreatedAt(\Criteria::DESC)
                ->limit(20)
                ->find()
        );
    }

    /**
     * @ApiDoc(
     *  section="Search Internet",
     *  resource = true,
     *  description="Add a search",
     * )
     *
     * @Rest\Post("/search")
     * @Rest\View(serializerGroups={"Default","detail"})
     */
    public function addSearchInternetAction(Request $request)
    {
        $term = $request->get('label');
        $this->get('bns.search_manager')->addSearch($term, $this->get('bns.right_manager')->getUserSession());
        return $this->view(null, Codes::HTTP_OK);
    }


    /**
     * @ApiDoc(
     *  section="Search",
     *  resource = true,
     *  description="Search on the site",
     * )
     *
     * @Rest\Post("/search/searcher")
     * @Rest\View(serializerGroups={"Default","media_search"})
     */
    public function postSearchAction(Request $request)
    {
        $searchManager = $this->get('search.searcher');
        $term = $request->get('term');
        $providers = $request->get('providers');

        if(!$term == null && $this->hasFeature('search_sdet_search_ent')) {
            $historic = new SearchHistoric();
            $historic->setUserId($this->getCurrentUserId())->setSearch($term)->save();
        }
        if (!count($providers)) {
            $providers = array_keys($searchManager->getProviders());
        }
       $result = $searchManager->searcher($term, $providers);
        return $result;

    }


    /**
     * @ApiDoc(
     *  section="Search",
     *  resource = true,
     *  description="Search on the site",
     * )
     *
     * @Rest\Get("/search/history")
     * @Rest\View(serializerGroups={"Default","media_search"})
     */
    public function getSearchAction()
    {
        if($this->hasFeature('search_sdet_search_ent')) {
            return SearchHistoricQuery::create()
                ->filterByUserId($this->getCurrentUserId())
                ->lastUpdatedFirst()
                ->limit(10)
                ->find();
        }

    }
}
