<?php
namespace BNS\App\ClassroomBundle\Statistic;
use BNS\App\StatisticsBundle\Services\StatisticsService;

/**
 * Service de statistiques dédiés au classroom bundle
 * Pour chaque action que l'on souhaite sauvegardé,
 * on crée une méthode dans le service
 *
 * @author florian.rotagnon@atos.net
 */
class ClassroomStatisticsService extends StatisticsService
{
    /**
     * création d'un compte eleve
     */
    public function createStudentAccount()
    {
        $this->increment("CLASSROOM_CREATE_STUDENT-ACCOUNT");
    }

    /**
     * création d'un groupe de travail
     */
    public function createGroup()
    {
        $this->increment("CLASSROOM_CREATE_GROUP");
    }

    /**
     * action de changement d'année
     */
    public function changeGrade()
    {
        $this->increment("CLASSROOM_CHANGE_GRADE");
    }
}
