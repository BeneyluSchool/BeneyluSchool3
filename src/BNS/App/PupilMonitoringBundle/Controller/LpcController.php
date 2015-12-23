<?php
namespace BNS\App\PupilMonitoringBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\PupilMonitoringBundle\Controller\CommonController;
use RecursiveIteratorIterator;
use BNS\App\PupilMonitoringBundle\Model\PupilLpcQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\PupilMonitoringBundle\Model\PupilLpcLinkQuery;

class LpcController extends CommonController
{
    /**
     * Filtre les utilisateurs visibles
     * @param boolean $isInFront
     * @return array Liste d'utilisateurs autorisés
     */
    protected function CheckUsers($isInFront = true)
    {       
        if(!$this->get('bns.right_manager')->hasRight(self::LPC_PERMISSION_FULL) && $isInFront){
            //Nous n'avons pas tous les droits en front : on compte les enfants dans ce groupe
            return $this->getAuthorisedChildren();
        }else{
            return $this->get('bns.right_manager')->getCurrentGroupManager()->getUsersByRoleUniqueName(self::PUPIL_ROLE, true);
        }
    }
    
    /**
     * @Template("BNSAppPupilMonitoringBundle:Lpc:index.html.twig")
     */
    public function commonIndexAction($canEdit,$isInFront)
    {
        $pupils = $this->checkUsers(!$canEdit);
        
        if(count($pupils) == 1 && !$canEdit)
        {
            return $this->forward(
                'BNSAppPupilMonitoringBundle:Lpc:commonPupil',
                array(
                    'login' => $pupils[0]->getLogin(),
                    'isInFront' => true,
                    'canEdit' => $canEdit
                )
            );
        }
        return array(
            'canEdit' => $canEdit,
            'pupils' => $pupils,
            'is_in_front' => $isInFront
        );
    }
    
    /**
     * @Template("BNSAppPupilMonitoringBundle:Lpc:pupil.html.twig")
     */
    public function commonPupilAction($login,$isInFront,$canEdit)
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
        $root = PupilLpcQuery::create()->findRoot();
        $links = PupilLpcLinkQuery::getOrderedLinks($user);
        return array(
            'user' => $user,
            'section' => 'user',
            'is_in_front' => $isInFront,
            'backLink' => count($authorisedUsers) > 1,
            'root' => $root,
            'links' => $links,
            'canEdit' => $canEdit 
        );
    }
    
}
