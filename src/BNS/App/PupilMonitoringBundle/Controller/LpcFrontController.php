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

class LpcFrontController extends CommonController
{
    /**
     * @Route("/livret", name="BNSAppPupilMonitoringBundle_lpc_front_index")
     * @Template()
     * @Rights("PUPILMONITORING_ACCESS")
     */
    public function indexAction()
    {
        return $this->forward(
            'BNSAppPupilMonitoringBundle:Lpc:commonIndex',
            array(
                'canEdit' => $this->get('bns.right_manager')->hasRight(self::LPC_PERMISSION_FULL),
                'isInFront' => true
            )
        );
    }
    
    /**
     * @Route("/livret/eleve/{login}", name="BNSAppPupilMonitoringBundle_lpc_front_pupil")
     * @Rights("PUPILMONITORING_ACCESS")
     */
    public function pupilAction($login)
    {
       return $this->forward(
            'BNSAppPupilMonitoringBundle:Lpc:commonPupil',
            array(
                'login' => $login,
                'isInFront' => true,
                'canEdit' => $this->get('bns.right_manager')->hasRight(self::LPC_PERMISSION_FULL),
            )
        );
    }
}
