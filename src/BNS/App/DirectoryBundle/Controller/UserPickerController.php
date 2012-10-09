<?php

namespace BNS\App\DirectoryBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Model\Group;

class UserPickerController extends Controller
{
    public function activateUserPickerAction()
    {
		$groupsICanView = $groups;
								
        return $this->render('BNSAppDirectoryBundle:Directory:index.html.twig', array(
			'groups'			=> $groupsICanView,
			'current_group'		=> $currentGroup,
			'is_userpicker'		=> false
		));
    }
	
	public function renderUserPickerAction(array $groups, Group $currentGroup)
	{
		return $this->render('BNSAppDirectoryBundle:Directory:index.html.twig', array(
			'groups'			=> $groups,
			'current_group'		=> $currentGroup,
			'is_userpicker'		=> true
		));
	}
}
