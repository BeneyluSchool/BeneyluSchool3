<?php

namespace BNS\App\SchoolBundle\Model;

use BNS\App\MailerBundle\Mailer\BNSMailer;
use BNS\App\SchoolBundle\Model\om\BaseClassroomSignal;
use BNS\App\CoreBundle\Right\BNSRightManager;

class ClassroomSignal extends BaseClassroomSignal
{

    public function init($reasonLabel, $classroomId, BNSRightManager $rightManager, BNSMailer $mailer)
    {
        $this->setReason($reasonLabel);
        $this->setSchoolId($rightManager->getCurrentGroupManager()->getGroup()->getId());
        $this->setClassroomId($classroomId);
        $this->setStatus(false);
        $this->setUserId($rightManager->getUserSessionId());
        $this->save();

        $mailer->send('CLASSROOM_SIGNAL', array(), $mailer->getAdminEmail(), 'fr');
    }

    public function check($response)
    {
        $this->setResponse($response);
        $this->setStatus(true);
        $this->save();
    }
}
