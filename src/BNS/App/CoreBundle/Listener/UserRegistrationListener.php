<?php
namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Model\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class UserRegistrationListener
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Pour controller la bonne saisie des utilisateurs de la version publique
     * Si l'utilisateur a un registrationStep != null, il doit forcément passer par là
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        /** @var User $user */
        if($this->container->get('bns.right_manager')->isAuthenticated()) {
            $user = $this->container->get('bns.right_manager')->getUserSession();

            // redirect to registration step, except when request matches login_path
            if ($user->getRegistrationStep() && '/' !== $event->getRequest()->getPathInfo()) {
                if (strpos($event->getRequest()->getRequestUri(), '/api/')) {
                    return;
                }

                $url = $this->container->get('router')->generate('user_front_registration_step', array('step' => $user->getRegistrationStep()));
                if ($url != $event->getRequest()->getRequestUri()) {
                    $event->setResponse(new RedirectResponse($url));
                }
            }
        }
    }

}
