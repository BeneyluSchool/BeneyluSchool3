<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Application\ApplicationManager;
use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\CoreBundle\Model\GroupQuery;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @deprecated not used anymore
 * Stores the locale of the user in the session after the
 * login. This can be used by the LocaleListener afterwards.
 */
class DisableApplicationListener
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var BNSUserManager
     */
    protected $userManager;

    /**
     * @var ApplicationManager
     */
    protected $applicationManager;

    /**
     * @var BNSGroupManager
     */
    protected $groupManager;

    public function __construct(Session $session, ApplicationManager $applicationManager, BNSUserManager $userManager, BNSGroupManager $groupManager)
    {
        $this->session = $session;
        $this->userManager = $userManager;
        $this->applicationManager = $applicationManager;
        $this->groupManager = $groupManager;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        $this->session->remove('disable_application_managment');
        if ($user instanceof User && $this->applicationManager->isEnabled()) {
            $this->userManager->setUser($user);
            $groups = $this->userManager->getFullRightsAndGroups();

            foreach ($groups as $groupData) {
                $group = GroupQuery::create()->findPk($groupData['group']['id']);
                if ($group) {
                    $info = $this->groupManager->setGroup($group)->getProjectInfo('disable_application_managment');
                    if (true === $info) {
                        $this->session->set('disable_application_managment', true);
                        $this->applicationManager->disable();
                        break;
                    }
                }
            }
        }
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            // don't do anything if it's not the master request
            return;
        }

        if (true === $this->session->get('disable_application_managment')) {
            $this->applicationManager->disable();
        }
    }


}
