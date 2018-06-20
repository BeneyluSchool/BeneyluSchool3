<?php
namespace BNS\App\LunchBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au lunch bundle
 */
class LunchStatisticsService extends StatisticsService
{
    public function visit()
    {
        $this->increment('LUNCH_VISIT');
    }
}
