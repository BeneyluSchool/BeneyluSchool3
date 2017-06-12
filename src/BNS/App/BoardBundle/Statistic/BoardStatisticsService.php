<?php
namespace BNS\App\BoardBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au board bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class BoardStatisticsService extends StatisticsService
{
    /**
     * Publication d'un message
     */
    public function publishMessage()
    {
        $this->increment("BOARD_MESSAGE_PUBLISH");
    }
}
