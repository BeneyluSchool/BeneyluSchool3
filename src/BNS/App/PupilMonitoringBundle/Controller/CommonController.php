<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Eymeric Taelman
 */

class CommonController extends Controller
{
    
    const ABSENCE_PERMISSION_PREFIX = 'PUPILMONITORING_ABSENCES';
    const ABSENCE_PERMISSION_BACK = 'PUPILMONITORING_ABSENCES_ACCESS_BACK';
    const ABSENCE_PERMISSION_FRONT = 'PUPILMONITORING_ABSENCES_ACCESS';
    const ABSENCE_PERMISSION_FULL = 'PUPILMONITORING_ABSENCES_FULL_ACCESS';
    const LPC_PERMISSION_PREFIX = 'PUPILMONITORING_LPC';
    const LPC_PERMISSION_BACK = 'PUPILMONITORING_LPC_ACCESS_BACK';
    const LPC_PERMISSION_FRONT = 'PUPILMONITORING_LPC_ACCESS';
    const LPC_PERMISSION_FULL = 'PUPILMONITORING_LPC_FULL_ACCESS';
    const PUPIL_ROLE = 'PUPIL';
    
    
    
    /*
     * Fonction redirigeant selon les droits que l'on a dans le module
     * Priorité aux absences, puis LPC
     */
    protected function redirectHome($front = true)
    {
        $rm = $this->get('bns.right_manager');
        $pattern = $front ? '_ACCESS' : '_ACCESS_BACK';
        if($rm->hasRight(self::ABSENCE_PERMISSION_PREFIX . $pattern))
        {
            $controller = $front ? 'AbsenceFront' : 'AbsenceBack';
            
        }elseif($rm->hasRight(self::LPC_PERMISSION_PREFIX . $pattern)){
            $controller = $front ? 'LPCFront' : 'LPCBack';
        }
        if(!isset($controller))
        {
            $rm->forbidIf(true);
        }
        return $this->forward('BNSAppPupilMonitoringBundle:' . $controller . ':index');
    }
    
    /**
     * Vérifie que la méthode est en ajax
     */
    protected function checkAjax()
    {
        if(!$this->getRequest()->isXmlHttpRequest())
        {
            $this->get('bns.right_manager')->forbidIf(true);
        }
    }
    
    /**
     * Récupère et vérifie un utilisateur, qu'on a le droit de manipuler
     */
    protected function getPupil($login,$forFront = true,$module = "ABSENCE")
    {
        $gm = $this->get('bns.group_manager');
        $rm = $this->get('bns.rightManager');
        $user = $this->get('bns.user_manager')->findUserByLogin($login);
        
        switch($module){
            case "ABSENCE":
                $authorisedGroups = $forFront ? $rm->getGroupsWherePermission(self::ABSENCE_PERMISSION_FRONT) : $rm->getGroupsWherePermission(self::ABSENCE_PERMISSION_BACK);
            break;
            case "LPC":
                $authorisedGroups = $forFront ? $rm->getGroupsWherePermission(self::LPC_PERMISSION_FRONT) : $rm->getGroupsWherePermission(self::LPC_PERMISSION_BACK);
            break;
        }
        
		
        foreach ($authorisedGroups as $group) {
            $gm->setGroup($group);
            if (in_array($user->getId(), $gm->getUsersIds())) {
                return $user;
            }
        }
        $this->get('bns.right_manager')->forbidIf(true);
    }
    
    /**
     * 
     * @return typeRécupère les utilisateurs qu'on a le droit de voir
     */
    protected function getAuthorisedChildren()
    {
        $rm = $this->get('bns.right_manager');
        $gm = $this->get('bns.group_manager');
        $um = $this->get('bns.user_manager');
        $group = $rm->getCurrentGroup();
        $gm->setGroup($group);
        $userIds = $gm->getUsersIds();
        $children = $um->getUserChildren($rm->getUserSession());
        $return = array();
        foreach($children as $child)
        {
            if(in_array($child->getId(),$userIds))
            {
                $return[] = $child;
            }
        }
        if(count($return) == 0)
        {
            $rm->forbidIf(true);
        }
        return $return;
    }

    /**
     * @Route("/", name="BNSAppPupilMonitoringBundle_front")
     * @Rights("PUPILMONITORING_ACCESS")
     */
    public function frontAction()
    {
       return $this->redirectHome(true);
    }
    
    /**
     * @Route("/gestion", name="BNSAppPupilMonitoringBundle_back")
     * @Rights("PUPILMONITORING_ACCESS_BACK")
     */
    public function backAction()
    {
        return $this->redirectHome(false);
    }
}
