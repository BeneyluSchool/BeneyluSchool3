<?php
namespace BNS\App\MessagingBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au messaging bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class MessagingStatisticsService extends StatisticsService
{
    /**
     * Envoi d'un message (peu importe le nombre de destinataires)
     */
    public function sendMessage()
    {
        $this->increment("MESSAGING_SEND_MESSAGE");
    }

    /**
     * Action de modération : autoriser, refuser, repasser en modération
     */
    public function moderateMessage()
    {
        $this->increment("MESSAGING_MODERATE_MESSAGE");
    }

    public function visit()
    {
        $this->increment('MESSAGING_VISIT');
    }

}
