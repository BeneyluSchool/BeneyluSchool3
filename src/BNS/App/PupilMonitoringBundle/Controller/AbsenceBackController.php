<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use BNS\App\PupilMonitoringBundle\Controller\AbsenceController;
use BNS\App\PupilMonitoringBundle\Model\PupilAbsenceQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\Rights;

/**
 * @author Eymeric Taelman
 */

class AbsenceBackController extends AbsenceController
{
    /**
     * Listing des absences en back pour la journée
     * @Route("/gestion/absences", name="BNSAppPupilMonitoringBundle_absence_back_index")
     * @Route("/journee/{date}", name="BNSAppPupilMonitoringBundle_absence_back_index_date")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function indexAction($date = null)
    {
        return $this->forward(
            'BNSAppPupilMonitoringBundle:Absence:commonIndex',
            array(
                'canEdit' => true,
                'date' => $date,
                'isInFront' => false
            )
        );
    }
    
    /**
     * Listing des absences en back 
     * @Route("/gestion/absences/semaine/{date}", name="BNSAppPupilMonitoringBundle_absence_back_week")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function weekAction($date)
    {
        if($date == null)
        {
            $date = date('Y-m-d');
        }
       return $this->forward(
            'BNSAppPupilMonitoringBundle:Absence:commonWeek',
            array(
                'canEdit' => true,
                'date' => $date,
                'isInFront' => false
            )
        );
    }

    /**
     * Fiche élève en back
     * @Route("/gestion/absences/eleve/{login}", name="BNSAppPupilMonitoringBundle_absence_back_pupil")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function pupilAction($login)
    {
       return $this->forward(
            'BNSAppPupilMonitoringBundle:Absence:commonPupil',
            array(
                'login' => $login,
                'isInFront' => false
            )
        );
    }
    
    /**
     * Gestion des demande d'absence
     * @Route("/gestion/absences/changement/{login}/{date}/{type}", name="BNSAppPupilMonitoringBundle_absence_back_toggle_absence", options={"expose": true})
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function toggleAbsenceAction($login,$date,$type)
    {
        $this->checkAjax();
        $user = $this->getPupil($login);
        PupilAbsenceQuery::handleAbsence($user,$date,$this->get('bns.right_manager')->getCurrentGroup(),$type);
        return new Response();
    }
    
    /**
     * Gestion des demandes de légitimité
     * @Route("/gestion/absences/changement-legitimite/{login}/{date}/{legitimate}", name="BNSAppPupilMonitoringBundle_absence_back_toggle_legitimate", options={"expose": true})
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function toggleLegitimateAction($login,$date,$legitimate)
    {
        $this->checkAjax();
        $user = $this->getPupil($login);
        PupilAbsenceQuery::handleLegitimate($user,$date,$this->get('bns.right_manager')->getCurrentGroup(),$legitimate);
        return new Response();
    }
    
    
}
