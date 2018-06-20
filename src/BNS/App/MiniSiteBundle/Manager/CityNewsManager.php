<?php

namespace BNS\App\MiniSiteBundle\Manager;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\MiniSiteBundle\Model\MiniSitePage;
use BNS\App\MiniSiteBundle\Model\MiniSitePageCityNewsQuery;

/**
 * Class CityNewsManager
 *
 * @package BNS\App\MiniSiteBundle\Manager
 */
class CityNewsManager
{

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    public function __construct(BNSGroupManager $groupManager)
    {
        $this->groupManager = $groupManager;
    }

    /**
     * @param MiniSitePage $page
     * @param string $alias
     * @return MiniSitePageCityNewsQuery
     */
    public function getCityNewsQueryForPage(MiniSitePage $page, $alias = 'mspn')
    {
        $pageId = $page->getId();
        $group = $page->getMiniSite()->getGroup();
        $listIds = [];
        foreach ($group->getDistributionListGroups() as $listGroup) {
            $listIds[] = $listGroup->getDistributionListId();
        }

        if ('CITY' === $group->getType()) {
            $groups = [$group]; // dummy collection so the foreach below finds the city directly
        } else {
            $groups = $this->groupManager->getUniqueAncestors($group);
        }

        foreach ($groups as $group) {
            if ('CITY' === $group->getType()) {
                /** @var MiniSite $cityMinisite */
                $cityMinisite = $group->getMiniSites()->getFirst();
                if ($cityMinisite) {
                    $cityPage = $cityMinisite->getCityPage();
                    if ($cityPage) {
                        $pageId = $cityPage->getId();
                    }
                }
                break;
            }
        }

        return MiniSitePageCityNewsQuery::create($alias)
            ->filterByIsAllSchools(true)
            ->_or()
            ->useMiniSitePageNewsDistributionListQuery(null, \Criteria::LEFT_JOIN)
                ->filterByListId($listIds)
            ->endUse()
            ->_and() // explicit AND because query is confused by ->use() after OR and wants to insert another OR
            ->filterByPageId($pageId)
            ->filterByStatus('PUBLISHED')
            ->filterByPublishedAt('today', \Criteria::LESS_EQUAL)
            ->filterByPublishedEndAt('today', \Criteria::GREATER_EQUAL)
            ->orderByIsPinned(\Criteria::DESC)
            ->orderByPublishedAt(\Criteria::DESC)
            ->groupById()
        ;
    }

}
