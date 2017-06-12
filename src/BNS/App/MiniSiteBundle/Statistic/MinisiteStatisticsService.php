<?php
namespace BNS\App\MiniSiteBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au minisite bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 * avec le même nom que celle utilisée dans le controller
 *
 * @author florian.rotagnon@atos.net
 */
class MinisiteStatisticsService extends StatisticsService
{
    /**
     * création d'une page statique
     */
    public function createStaticPage()
    {
        $this->increment("MINISITE_CREATE_STATIC-PAGE");
    }

    /**
     * création d'une page dynamique
     */
    public function createDynamicPage()
    {
        $this->increment("MINISITE_CREATE_DYNAMIC-PAGE");
    }

    /**
     * création d'un article d'une page dynamique
     */
    public function createDynamicPageArticle()
    {
        $this->increment("MINISITE_CREATE_DYNAMIC-PAGE-ARTICLE");
    }

    /**
     * mise à jour d'une page statique
     */
    public function updateStaticPage()
    {
            $this->increment("MINISITE_UPDATE_STATIC-PAGE");
    }
}
