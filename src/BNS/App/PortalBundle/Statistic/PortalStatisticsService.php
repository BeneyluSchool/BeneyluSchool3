<?php
namespace BNS\App\PortalBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au portal bundle
 */
class PortalStatisticsService extends StatisticsService
{
    public function visit()
    {
        $this->increment('PORTAL_VISIT');
    }
}
