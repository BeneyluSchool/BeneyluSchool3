<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CommonController extends Controller
{

    protected function checkAjax()
    {
        if(!$this->getRequest()->isXmlHttpRequest())
        {
            $this->get('bns.right_manager')->forbidIf(true);
        }
    }

    protected function checkClassroom($classroomId)
    {
        $rm = $this->get('bns.right_manager');
        /* @var $gm \BNS\App\CoreBundle\Group\BNSGroupManager */
        $gm = $rm->getCurrentGroupManager();
        $schoolId = $gm->getGroup()->getId();
        $gm->setGroupById($classroomId);
        $gm->getParent();
        $rm->forbidIf($gm->getParent()->getId() != $schoolId);
    }

    protected function checkUser($userId,$rolesUniqueName)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        $um = $this->get('bns.user_manager');
        $um->setUserById($userId);
        if(!is_array($rolesUniqueName))
        {
            $rolesUniqueName = array($rolesUniqueName);
        }
        $ok = false;
        foreach($rolesUniqueName as $roleUniqueName)
        {
            if($um->hasRoleInGroup($this->get('bns.right_manager')->getCurrentGroupId(),$roleUniqueName))
            {
                $ok = true;
            }
        }

        if(!$ok)
        {
            $this->get('bns.right_manager')->forbidIf(true);
        }
    }

    protected function getClassroomForUser($userId)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        /* @var $gm \BNS\App\CoreBundle\Group\BNSGroupManager */
        $um = $this->get('bns.user_manager');
        $gm = $this->get('bns.group_manager');

        $user = $um->findUserById($userId);
        $um->setUser($user);
        foreach($um->getGroupsUserBelong() as $group)
        {
            if($group->getGroupType()->getType() == 'CLASSROOM')
            {
                $gm->setGroup($group);
                if($gm->getParent()->getId() == $this->get('bns.right_manager')->getCurrentGroupId())
                {
                    return $group;
                }
            }
        }
        return false;
    }

    protected function isUserValidated($userId)
    {
        /* @var $um \BNS\App\CoreBundle\User\BNSUserManager */
        /* @var $gm \BNS\App\CoreBundle\Group\BNSGroupManager */
        $um = $this->get('bns.user_manager');
        $gm = $this->get('bns.group_manager');

        $user = $um->findUserById($userId);
        $um->setUser($user);
        foreach($um->getGroupsUserBelong() as $group)
        {
            if($group->getGroupType()->getType() == 'CLASSROOM')
            {
                if($group->isValidated())
                {
                    return true;
                }
            }
        }
        return false;
    }
}