<?php

namespace BNS\App\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

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

        return $this->render('BNSAppMainBundle:DockBar:index.html.twig', array(
            'context_modules'           => $modules['context'],
            'constant_modules'          => $modules['global'],
            'current_module'            => $modules['current_module'],
            'is_in_front'               => $is_in_front,
            'moduleContextBackAccess'   => $modules['moduleContextBackAccess'],           
            'groupsContext'             => $rightManager->getGroups(false),
			'currentGroupType'			=> $rightManager->getCurrentGroupType(),
			'currentGroupRoute'			=> $rightManager->getRedirectRouteOfCurrentGroup($is_in_front)
        ));
    }
}

