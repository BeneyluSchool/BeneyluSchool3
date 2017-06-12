<?php
namespace BNS\App\WorkshopBundle\Statistic;

use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class WorkshopStatisticsService extends StatisticsService
{
    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('WORKSHOP_VISIT');
        $this->enableCascadeParentGroup();
    }
}
