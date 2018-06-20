<?php
namespace BNS\App\LiaisonBookBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au liaisonbook bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class LiaisonBookStatisticsService extends StatisticsService
{
    /**
     * Publication d'un message
     */
    public function newMessage()
    {
        $this->increment("LIAISONBOOK_PUBLISH_MESSAGE");
    }

    /**
     * Signature d'un travail
     */
    public function newSignature()
    {
        $this->increment("LIAISONBOOK_CREATE_SIGN");
    }

    public function visit()
    {
        $this->increment('LIAISONBOOK_VISIT');
    }

}
