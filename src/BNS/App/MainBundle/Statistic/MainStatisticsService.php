<?php
namespace BNS\App\MainBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au main bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class MainStatisticsService extends StatisticsService
{
    /**
     * Connexion a la plateforme
     */
    public function connect()
    {
        $this->increment("MAIN_CONNECT_PLATFORM");
    }

    /**
     * Regénération d'un mot de pass
     */
    public function regeneratePassword()
    {
        $this->increment("MAIN_REGENERATE_PASSWORD");
    }

    /**
     * Inscription d'une classe
     */
    public function newClass()
    {
        $this->increment("MAIN_REGISTER_CLASS");
    }

}
