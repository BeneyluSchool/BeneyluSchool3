<?php
namespace BNS\App\SearchBundle\Statistic;

use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class SearchStatisticsService extends StatisticsService
{
    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('SEARCH_VISIT');
        $this->enableCascadeParentGroup();
    }
}
