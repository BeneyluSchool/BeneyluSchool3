<?php
namespace BNS\App\GroupBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au group bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class GroupStatisticsService extends StatisticsService
{
    /**
     * Création d'un groupe
     */
    public function newGroup($type)
    {
        $this->increment("GROUP_CREATE_GROUP", $type);
    }
}
