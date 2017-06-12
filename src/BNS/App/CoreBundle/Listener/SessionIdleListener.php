<?php
namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Model\User;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class SessionIdleListener
{
    /**
     * @var int idle max time in seconds
     */
    protected $maxIdleTime;

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(TokenStorageInterface $tokenStorage, SessionInterface $session, RouterInterface $router, $maxIdleTime)
    {
        $this->tokenStorage = $tokenStorage;
        $this->session = $session;
        $this->maxIdleTime = (int)$maxIdleTime;
        $this->router = $router;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST != $event->getRequestType()) {
            return;
        }

        // do not expired unauthenticated session
        if (!($token = $this->tokenStorage->getToken())) {
            return ;
        }

        $user = $token->getUser();
        if ($token->isAuthenticated() && is_object($user) && $user instanceof User && $this->maxIdleTime > 0) {
            $this->session->start();

            $lapse = time() - $this->session->getMetadataBag()->getLastUsed();
            if ($lapse > $this->maxIdleTime) {
                // invalidate the session because it has expired
                $this->tokenStorage->setToken(null);
                $this->session->invalidate();

                // force disconnect from Auth
                $request = $event->getRequest();
                $redirectUrl = $this->router->generate('disconnect_user');
                if ($this->isXhrOrApi($request)) {
                    $response = new Response('', Codes::HTTP_UNAUTHORIZED);
                    $response->headers->set('Location', $redirectUrl);
                } else {
                    $response = new RedirectResponse($redirectUrl);
                }
                $event->setResponse($response);
            }
        }
    }

    protected function isXhrOrApi(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        if (preg_match('/^\/api/', $request->getPathInfo())) {
            return true;
        }

        return false;
    }
}
