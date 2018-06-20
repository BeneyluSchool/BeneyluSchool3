<?php
namespace BNS\App\CalendarBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au calendar bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class CalendarStatisticsService extends StatisticsService
{
    /**
     * Evènement créé
     */
    public function newEvent()
    {
        $this->increment("CALENDAR_CREATE_EVENT");
    }

    public function visit()
    {
        $this->increment('CALENDAR_VISIT');
    }
}
