<?php
namespace BNS\App\UserDirectoryBundle\Statistic;

use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au directory bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 */
class UserDirectoryStatisticsService extends StatisticsService
{
    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('USERDIRECTORY_VISIT');
        $this->enableCascadeParentGroup();
    }

}
