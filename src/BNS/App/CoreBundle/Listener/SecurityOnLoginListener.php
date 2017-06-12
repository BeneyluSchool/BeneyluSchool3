<?php
namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\User\BNSUserManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class SecurityOnLoginListener
{
    /** @var BNSUserManager  */
    protected $userManager;

    /** @var TokenStorageInterface  */
    protected $tokenStorage;

    public function __construct(BNSUserManager $userManager, TokenStorageInterface $tokenStorage)
    {
        $this->userManager = $userManager;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * This is used to reset user right only on connection
     *
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $this->getUser();
        if ($user) {
            // reset user cache on logon this should be done only once
            $this->userManager->setUser($user)->resetRights();
        }
    }

    protected function getUser()
    {
        $token = $this->tokenStorage->getToken();
        if ($token) {
            return $token->getUser();
        }

        return null;
    }
}
