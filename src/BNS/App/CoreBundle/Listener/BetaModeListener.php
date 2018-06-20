<?php

namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Beta\BetaManager;
use BNS\App\CoreBundle\Exception\WrongBetaModeException;
use BNS\App\CoreBundle\Model\User;
use BNS\CommonBundle\Security\Logout\Logout;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Templating\EngineInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BetaModeListener
{
    /**
     * @var BetaManager
     */
    protected $betaManager;

    /**
     * @var EngineInterface
     */
    protected $templateEngine;

    /** @var Logout  */
    protected $logout;

    public function __construct(BetaManager $betaManager, EngineInterface $templateEngine, Logout $logout)
    {
        $this->betaManager = $betaManager;
        $this->templateEngine = $templateEngine;
        $this->logout = $logout;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        if (!$this->betaManager->isBetaModeAllowed() && !$this->betaManager->isBetaModeAllowedByStatus() && !$this->betaManager->isBetaModeEnabled()) {
            return;
        }

        $token = $event->getAuthenticationToken();
        if ($token instanceof RememberMeToken) {
            // do not redirect user when remember Me login occurred
            return;
        }

        /** @var User $user */
        $user = $token->getUser();
        if (!$user || !$user instanceof User) {
            return;
        }

        if ($user->getBeta() && !$this->betaManager->isBetaModeEnabled()) {
            // redirect to beta mode
            throw new WrongBetaModeException($user->getBeta(), $user);
        } elseif (!$user->getBeta() && $this->betaManager->isBetaModeEnabled()) {
            // redirect to normal mode
            throw new WrongBetaModeException($user->getBeta(), $user);
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();
        if ($exception instanceof WrongBetaModeException) {
            if ($exception->getBetaMode()) {
                $url = $this->betaManager->generateBetaRoute('home', ['user_id' => $exception->getUser()->getId()]);
            } else {
                $url = $this->betaManager->generateNormalRoute('home', ['user_id' => $exception->getUser()->getId()]);
            }
            // prevent user from keeping connected
            $this->logout->logout();

            $request = $event->getRequest();
            if ($request->isXmlHttpRequest() || $request->get('_xhr_call', false)) {
                $response =  new JsonResponse([
                    'navigate' => true,
                    'redirect_url' => $url
                ], Codes::HTTP_OK);
            } else {
                $content = $this->templateEngine->render('BNSAppMainBundle:Logon:refresh.html.twig', ['redirect' => $url]);
                $response = new Response($content);
            }

            // force code 200 and not 500
            $response->headers->set('X-Status-Code', 200);
            $event->setResponse($response);
        }
    }
}
