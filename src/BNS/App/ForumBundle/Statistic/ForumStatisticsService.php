<?php
namespace BNS\App\ForumBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au forum bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class ForumStatisticsService extends StatisticsService
{
    /**
     * Discussion créée
     */
    public function newTopic()
    {
        $this->increment("FORUM_CREATE_TOPIC");
    }

    /**
     * Message dans discussion publié
     */
    public function newMessage()
    {
        $this->increment("FORUM_CREATE_MESSAGE");
    }

    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('FORUM_VISIT');
        $this->enableCascadeParentGroup();
    }
}
