<?php

namespace BNS\App\PortalBundle\ApiController;


use BNS\App\CoreBundle\Controller\BaseApiController;

use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\MiniSiteBundle\Model\MiniSiteQuery;
use BNS\App\MiniSiteBundle\Model\MiniSitePageQuery;
use BNS\App\UserDirectoryBundle\Model\DistributionListGroupQuery;
use FOS\RestBundle\Controller\Annotations as Rest;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\PortalBundle\Model\PortalWidget;
use BNS\App\PortalBundle\Model\PortalWidgetQuery;

use BNS\App\PortalBundle\Model\PortalWidgetGroupQuery;
use FOS\RestBundle\Request\ParamFetcher;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PortalApiController
 *
 * @package BNS\App\PortalBundle\ApiController
 */
class PortalApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Portail",
     *  resource = true,
     *  description="Get a group's minisites",
     * )
     *
     * @Rest\Get("/portal/{groupId}/minisites")
     * @Rest\QueryParam(name="search", description="group name filter")
     * @Rest\View(serializerGroups={"Default","detail","portal_widget"})
     */
    public function getMinisitesAction($groupId, ParamFetcherInterface $paramFetcher)
    {
        $group = GroupQuery::create()->findOneById($groupId);

        $gm = $this->get('bns.group_manager');
        $gm->setGroup($group);
        $subgroupsSchool = $gm->getSubgroupsByGroupType('SCHOOL', false);
        if (GroupTypeQuery::create()->findOneByType('STRUCTURE_ACCUEIL')) {
            $subgroupsStructure = $gm->getSubgroupsByGroupType('STRUCTURE_ACCUEIL', false);
        } else {
            $subgroupsStructure = [];
        }
        $extractId = function($item) { return (int) $item['id']; };

        $schoolIds = GroupQuery::create()
            ->filterById(array_map($extractId, $subgroupsSchool))
            ->_if($this->getParameter('check_group_enabled'))
                ->filterByEnabled(true)
            ->_endif()
            ->select('Id')
            ->find()
            ->getArrayCopy()
        ;

        $groupIds = array_merge($schoolIds, array_map($extractId, $subgroupsStructure));

        if ($paramFetcher->get('search')) {
            $search = '*'.$paramFetcher->get('search').'*';
        } else {
            $search = null;
        }
        return MiniSiteQuery::create()
            ->useGroupQuery()
                ->filterById($groupIds)
                ->_if($search)
                    ->filterByLabel($search)
                ->_endif()
            ->endUse()
            ->find();
    }

    /**
     * @ApiDoc(
     *  section="Portail",
     *  resource = true,
     *  description="Get a portal's widget by id",
     * )
     *
     * @Rest\Get("/portal/{groupId}/widget/{widgetId}")
     * @Rest\View(serializerGroups={"Default","detail", "portal_widget"})
     */
    public function getWidgetAction($groupId, $widgetId)
    {
        $widget =  PortalWidgetQuery::create()
            ->filterById($widgetId)
            ->_if(!$this->get('bns.right_manager')->hasRight('PORTAL_ACCESS_BACK', $groupId))
                ->filterByEnabled(true)
            ->_endif()
            ->usePortalWidgetGroupQuery()
                ->usePortalQuery()
                    ->filterByGroupId($groupId)
                ->endUse()
            ->endUse()
            ->findOne()
        ;
        if (!$widget) {
            throw $this->createNotFoundException();
        }

        return $widget;
    }


    /**
     * @ApiDoc(
     *  section="Portail",
     *  resource = true,
     *  description="Get a portal's minisite widget by id with pagination",
     * )
     *
     * @Rest\Get("/portal/{groupId}/minisites/{id}")
     * @Rest\QueryParam("page", requirements="\d*", default="1")
     * @Rest\QueryParam("limit", requirements="\d*", default="25")
     * @Rest\View(serializerGroups={"Default","detail"})
     *
     * @param int $groupId
     * @param int $id
     * @param ParamFetcher $paramFetcher
     * @return array|View
     */
    public function getMinisitesWidgetAction($groupId, $id, ParamFetcher $paramFetcher)
    {
        $widget = PortalWidgetQuery::create()->findPk($id);
        if (!$widget || $widget->getType() !== 'MINISITE') {
            return $this->view(['message' => 'Invalid widget type'], Codes::HTTP_BAD_REQUEST);
        }

        $extractId = function($item) {
            return $item['id'];
        };

        $uData = $widget->getDatas();
        if ($widget->getData('all')) {
            $groupIds = array_map($extractId, $this->get('bns.group_manager')->setGroupById((int)$groupId)->getSubgroupsByGroupType('SCHOOL', false));

            $groupIds = GroupQuery::create()
                ->filterById($groupIds)
                ->_if($this->getParameter('check_group_enabled'))
                    ->filterByEnabled(true)
                ->_endif()
                ->select('Id')
                ->find()
                ->getArrayCopy()
            ;
        } else {
            $data = $widget->getData('lists');
            $groupIds = DistributionListGroupQuery::create()
                ->filterByDistributionListId($data, \Criteria::IN)
                ->select(['groupId'])
                ->find()
                ->toArray();
        }

        $minisites = MiniSiteQuery::create()
            ->filterByGroupId($groupIds)
            ->orderByTitle()
            ->find();

        $pagesCounts = MiniSitePageQuery::create()
            ->filterByMiniSite($minisites)
            ->filterByIsPublic(true)
            ->filterByIsActivated(true)
            ->groupByMiniSiteId()
            ->withColumn('COUNT(*)', 'pages')
            ->select(['MiniSiteId', 'pages'])
            ->find()
            ->toArray('MiniSiteId');

        $all = [];
        foreach ($minisites as $minisite) {
            if (isset($pagesCounts[$minisite->getId()]['pages']) && $pagesCounts[$minisite->getId()]['pages'] > 0) {
                $all[] = $minisite;
            }
        }

        $total = count($all);
        $limit = intval($paramFetcher->get('limit'));
        $page = intval($paramFetcher->get('page'));
        $pages = ceil($total/$limit);
        $offset = ($page - 1) * $limit;
        $pagination = array('page' => $page, 'pages' => $pages, 'total' => $total );
        $minisites = array_slice($all, $offset, $limit);
        $content = array('pagination' => $pagination, 'config' => $uData, 'minisites' => $minisites);

        return $content;
    }


}
