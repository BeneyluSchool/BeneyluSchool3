<?php
namespace BNS\App\GPSBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au GPS bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 * 
 * @author florian.rotagnon@atos.net
 */
class GPSStatisticsService extends StatisticsService
{
    /**
     * Création d'un lieux
     */
    public function newPlace()
    {
        $this->increment("GPS_CREATE_PLACE");
    }
    
    /**
     * Recherche dans le GPS
     */
    public function newSearch()
    {
        $this->increment("GPS_DO_SEARCH");
    }
}

?>
