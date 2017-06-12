<?php
namespace BNS\App\HomeworkBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au homework bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class HomeworkStatisticsService extends StatisticsService
{
    /**
     * Publication d'un travail
     */
    public function newWork()
    {
        $this->increment("HOMEWORK_CREATE_WORK");
    }

    /**
     * Validation d'un travail
     */
    public function validateWork()
    {
        $this->increment("HOMEWORK_VALIDATE_WORK");
    }

    public function visit()
    {
        $this->disableCascadeParentGroup();
        $this->increment('HOMEWORK_VISIT');
        $this->enableCascadeParentGroup();
    }
}
