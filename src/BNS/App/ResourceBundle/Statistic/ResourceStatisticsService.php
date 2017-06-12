<?php
namespace BNS\App\ResourceBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au resource bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class ResourceStatisticsService extends StatisticsService
{
    /**
     * recherche
     */
    public function newSearch()
    {
        $this->increment("RESOURCE_DO_SEARCH");
    }

    /**
     * document ajouté
     */
    public function newFile()
    {
        $this->increment("RESOURCE_ADD_FILE");
    }

    /**
     * Evolution de l'espace autorisé
     */
    public function changeAllowedSpace()
    {
        $this->increment("RESOURCE_CHANGE_ALLOWED-SPACE");
    }

}
