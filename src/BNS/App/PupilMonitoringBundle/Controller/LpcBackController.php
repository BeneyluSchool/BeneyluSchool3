<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\PupilMonitoringBundle\Model\PupilLpcLinkQuery;
use Symfony\Component\HttpFoundation\Response;
use BNS\App\CoreBundle\Annotation\Rights;

/**
 * @Route("/gestion")
 * @author Eymeric Taelman
 */

class LpcBackController extends LpcController
{
    /**
     * Listing des absences en back pour le groupe en cours
     * @Route("/livret", name="BNSAppPupilMonitoringBundle_lpc_back_index")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function indexAction()
    {
        return $this->forward(
            'BNSAppPupilMonitoringBundle:Lpc:commonIndex',
            array(
                'canEdit' => true,
                'isInFront' => false
                
            )
        );
    }
    
    /**
     * Fiche élève en back
     * @Route("/livret/eleve/{login}", name="BNSAppPupilMonitoringBundle_lpc_back_pupil")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function pupilAction($login)
    {
       return $this->forward(
            'BNSAppPupilMonitoringBundle:Lpc:commonPupil',
            array(
                'login' => $login,
                'canEdit' => true,
                'isInFront' => false
            )
        );
    }
    
    /**
     * Ajout d'une date pour un item
     * @Route("/livret/selection/{login}/{lpcSlug}/{date}", name="BNSAppPupilMonitoringBundle_lpc_back_select" , options={"expose": true})
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function selectAction($login,$lpcSlug,$date)
    {
        $this->checkAjax();
        $user = $this->getPupil($login,false,"LPC");
        PupilLpcLinkQuery::handleLink($user,$lpcSlug,$date);
        return new Response();
    }
}
