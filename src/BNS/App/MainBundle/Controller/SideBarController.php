<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Access\BNSAccess;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class SideBarController extends Controller
{
	/**
	 * @Template()
	 */
    public function indexAction($group)
    {
        $activeModule = 'classroom';
        $uri =  $this->getRequest()->getUri();

        if (strstr($uri, '/school'))
        {
                $activeModule = 'school';
        }

        $location = 'index';
        if ('classroom' == $activeModule)
        {
                if (strstr($uri, '/team'))
                {
                        $location = 'team';
                }
        }
        elseif ('school' == $activeModule)
        {
                if (strstr($uri, '/user'))
                {
                        $location = 'user';
                }
                elseif (strstr($uri, '/classroom'))
                {
                        $location = 'classroom';
                }
                elseif (strstr($uri, '/right-manager'))
                {
                        $location = 'right-manager';
                }
        }

        $userName = $this->getUser()->getFullName();

        return array(
                'group'			=> $group,
                'activeModule' 	=> $activeModule,
                'location'		=> $location,
        );
    }
}
