<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\PupilMonitoringBundle\Controller\CommonController;

/**
 * @author Eymeric Taelman
 */

class AbsenceFrontController extends CommonController
{
    /**
     * @Route("/absence/", name="BNSAppPupilMonitoringBundle_absence_front_index")
     * @Route("/journee/{date}", name="BNSAppPupilMonitoringBundle_absence_front_index_date")
     * @Template()
     * @Rights("PUPILMONITORING_ACCESS")
     */
    public function indexAction($date = null)
    {
        return $this->forward(
            'BNSAppPupilMonitoringBundle:Absence:commonIndex',
            array(
                'canEdit' => $this->get('bns.right_manager')->hasRight(self::ABSENCE_PERMISSION_FULL),
                'date' => $date,
                'isInFront' => true
            )
        );
    }
    
    /**
     * @Route("/absence/semaine/{date}", name="BNSAppPupilMonitoringBundle_absence_front_week")
     * @Rights("PUPILMONITORING_ACCESS")
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
                'canEdit' => $this->get('bns.right_manager')->hasRight(self::ABSENCE_PERMISSION_FULL),
                'date' => $date,
                'isInFront' => true
            )
        );
    }
    
    /**
     * @Route("/absence/eleve/{login}", name="BNSAppPupilMonitoringBundle_absence_front_pupil")
     * @Rights("PUPILMONITORING_ACCESS")
     */
    public function pupilAction($login)
    {
       return $this->forward(
            'BNSAppPupilMonitoringBundle:Absence:commonPupil',
            array(
                'login' => $login,
                'isInFront' => true
            )
        );
    }
}
