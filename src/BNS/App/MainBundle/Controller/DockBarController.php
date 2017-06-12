<?php

namespace BNS\App\MainBundle\Controller;
use BNS\App\Debug;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use BNS\App\GroupBundle\Controller\BackController;

class DockBarController extends Controller
{
    /**
     * 
     */
    public function indexAction($module_unique_name = null, $is_in_front = null)
    {
        $rightManager = $this->get('bns.right_manager');
        
		// Trois parties : les modules "incontextable" (constants), les modules "contextables" et le module en cours
        $modules = $rightManager->getDockModules($module_unique_name, $is_in_front);

        $showInactiveSchoolPage = false;

        if($this->get('service_container')->hasParameter('bns.enable_register') && $this->get('service_container')->getParameter('bns.enable_register') == true)
        {
            if($rightManager->getCurrentGroupManager()->isOnPublicVersion())
            {
                if($rightManager->hasRight('CLASSROOM_ACCESS_BACK'))
                {
                    if(!$rightManager->getCurrentGroupManager()->getParent()->isPremium())
                    {
                        $showInactiveSchoolPage = true;
                    }
                }
            }
        }

        return $this->render('BNSAppMainBundle:DockBar:index.html.twig', array(
            'context_modules'           => $modules['context'],
            'constant_modules'          => $modules['global'],
            'current_module'            => $modules['current_module'],
            'is_in_front'               => $is_in_front,
            'moduleContextBackAccess'   => $modules['moduleContextBackAccess'],           
            'groupsContext'             => $rightManager->getGroups(false),
            'currentGroupType'		    => $rightManager->getCurrentGroupType(),
            'currentGroup'		        => $rightManager->getCurrentGroup(),
            'currentGroupRoute'		    => $rightManager->getRedirectRouteOfCurrentGroup(!$is_in_front),
            'rightManager'              => $rightManager,
            'nbNotifInfo'               => $rightManager->getNbNotifInfo(),
            'showInactiveSchoolPage'   => $showInactiveSchoolPage
        ));
    }
}

