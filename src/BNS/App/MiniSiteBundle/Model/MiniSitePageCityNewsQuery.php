<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePageCityNewsQuery;


/**
 * Skeleton subclass for representing a query for one of the subclasses of the 'mini_site_page_news' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.MiniSiteBundle.Model
 */
class MiniSitePageCityNewsQuery extends BaseMiniSitePageCityNewsQuery
{

    public function buildStatusFilter(array $statuses)
    {
        $actualStatuses = array_intersect($statuses, ['DRAFT', 'PUBLISHED']);

        if (count($actualStatuses) > 0) {
            parent::buildStatusFilter($actualStatuses);
        }

        if (in_array('PUBLISHED', $statuses)) {
            $this->filterByPublishedAt('today', \Criteria::LESS_EQUAL)->filterByPublishedEndAt('today', \Criteria::GREATER_EQUAL);
        }

        if (in_array('PUBLISHED_FUTURE', $statuses)) {
            $this->_or()->filterByPublishedAt('today', \Criteria::GREATER_THAN);
        }
        if (in_array('PUBLISHED_PAST', $statuses)) {
            $this->_or()->filterByPublishedEndAt('today', \Criteria::LESS_THAN);
        }

        return $this;
    }

} // MiniSitePageCityNewsQuery
