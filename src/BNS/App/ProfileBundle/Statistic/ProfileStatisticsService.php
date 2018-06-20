<?php
namespace BNS\App\ProfileBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au profile bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class ProfileStatisticsService extends StatisticsService
{
    /**
     * Publication d'un statut
     */
    public function newStatus()
    {
        $this->increment("PROFILE_CREATE_STATUS");
    }

    /**
     * Publication d'un commentaire sur un statut
     */
    public function newComment()
    {
        $this->increment("PROFILE_CREATE_COMMENT");
    }

    public function visit()
    {
        $this->increment('PROFILE_VISIT');
    }
}
