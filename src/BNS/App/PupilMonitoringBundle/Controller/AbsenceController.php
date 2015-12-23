<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use BNS\App\PupilMonitoringBundle\Controller\CommonController;
use BNS\App\PupilMonitoringBundle\Model\PupilAbsenceQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\Rights;

class AbsenceController extends CommonController
{
    /**
     * Filtre les utilisateurs visibles
     * @param boolean $isInFront
     * @return array Liste d'utilisateurs autorisés
     */
    protected function CheckUsers($isInFront = true)
    {       
        if(!$this->get('bns.right_manager')->hasRight(self::ABSENCE_PERMISSION_FULL) && $isInFront){
            //Nous n'avons pas tous les droits en front : on compte les enfants dans ce groupe
            return $this->getAuthorisedChildren();
        }else{
            return $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName(self::PUPIL_ROLE, true);
        }
    }
    
    /**
     * @Template("BNSAppPupilMonitoringBundle:Absence:index.html.twig")
     */
    public function commonIndexAction($canEdit, $date, $isInFront)
    {
        /**
         * Calcul des dates en évitant les dimanches
         */
        if($date == null)
        {
            $date = date('Y-m-d');
        }
        
        //Si dimanche on prend le lendemain
        if(date('N',strtotime($date)) == 7)
        {
            $date =  date('Y-m-d',strtotime($date)+86400);
        }
        
        //Calcul journée précédente sauf dimanche
        $dateBefore =  date('Y-m-d',strtotime($date)-86400);
        if(date('N',strtotime($dateBefore)) == 7)
        {
            $dateBefore =  date('Y-m-d',strtotime($dateBefore)-86400);
        }
        
        //Calcul journée suivante sauf dimanche
        $dateAfter =  date('Y-m-d',strtotime($date)+86400);
        if(date('N',strtotime($dateAfter)) == 7)
        {
            $dateAfter =  date('Y-m-d',strtotime($dateAfter)+86400);
        }
        
        $rm = $this->get('bns.right_manager');
        
        $orderedAbsences = PupilAbsenceQuery::getOrderedAbsences($date,$rm->getCurrentGroupId());
        
        $pupils = $this->checkUsers(!$canEdit);
        
        if(count($pupils) == 1 && !$canEdit)
        {
            return $this->forward(
                'BNSAppPupilMonitoringBundle:Absence:commonPupil',
                array(
                    'login' => $pupils[0]->getLogin(),
                    'isInFront' => true
                )
            );
        }

        return array(
            'date' => $date,
            'dateBefore' => $dateBefore,
            'dateAfter' => $dateAfter,
            'canEdit' => $canEdit,
            'pupils' => $pupils,
            'orderedAbsences' => $orderedAbsences,
            'canEdit' => $canEdit,
            'is_in_front' => $isInFront
        );
    }
    
    /**
     * @Template("BNSAppPupilMonitoringBundle:Absence:week.html.twig")
     */
    public function commonWeekAction($canEdit, $date, $isInFront)
    {
        /**
         * Calcul des dates en évitant les dimanches
         */
        if($date == null)
        {
            $date = date('Y-m-d');
        }
        
        $datetemp = explode("-",$date); 
        
        $week = date("Y-m-d", mktime(0, 0, 0, $datetemp[1], $datetemp[2] - Date('w',strtotime($date)) + 1, $datetemp[0]));
        $weekBefore = date("Y-m-d", mktime(0, 0, 0, $datetemp[1], $datetemp[2] - Date('w',strtotime($date)) + 1 - 7, $datetemp[0]));
        $weekAfter = date("Y-m-d", mktime(0, 0, 0, $datetemp[1], $datetemp[2] - Date('w',strtotime($date)) + 1 + 7, $datetemp[0]));
        
        $rm = $this->get('bns.right_manager');
        
        $orderedAbsences = PupilAbsenceQuery::getOrderedAbsences($week,$rm->getCurrentGroupId(),$weekAfter);
        
        $pupils = $this->checkUsers(!$canEdit);
        
        if(count($pupils) == 1 && !$canEdit)
        {
            return $this->forward(
                'BNSAppPupilMonitoringBundle:Absence:commonPupil',
                array(
                    'login' => $pupils[0]->getLogin(),
                    'isInFront' => $isInFront
                )
            );
        }
        
        return array(
            'week' => $week,
            'weekBefore' => $weekBefore,
            'weekAfter' => $weekAfter,
            'canEdit' => $canEdit,
            'pupils' => $pupils,
            'orderedAbsences' => $orderedAbsences,
            'is_in_front' => $isInFront
        );
    }
    
    /**
     * @Template("BNSAppPupilMonitoringBundle:Absence:pupil.html.twig")
     */
    public function commonPupilAction($login,$isInFront)
    {
        $authorisedUsers = $this->checkUsers($isInFront);
        $continue = false;
        foreach($authorisedUsers as $authorisedUser)
        {
            if($authorisedUser->getlogin() == $login)
            {
                $continue = true;
            }
        }
        $this->get('bns.right_manager')->forbidIf(!$continue);
        $user = $this->getPupil($login);
        $absences = PupilAbsenceQuery::getPupilAbsences($user,$this->get('bns.right_manager')->getCurrentGroupId());
        return array(
            'user' => $user,
            'absences' => $absences,
            'section' => 'user',
            'is_in_front' => $isInFront,
            'backLink' => count($authorisedUsers) > 1
        );
    }
    
}
