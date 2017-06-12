<?php
namespace BNS\App\CoreBundle\Listener;

use BNS\App\CoreBundle\Api\BNSApi;
use BNS\App\CoreBundle\Beta\BetaManager;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiCacheSecurityListener
{
    /**
     * @var BetaManager
     */
    protected $betaManager;

    /**
     * @var BNSApi
     */
    protected $api;

    public function __construct(BetaManager $betaManager, BNSApi $api)
    {
        $this->betaManager = $betaManager;
        $this->api = $api;
    }


    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (preg_match('#^/api/[0-9]+\.?[0-9]*/cache/#', $request->getPathInfo())) {

            if (!$this->betaManager->isBetaModeAllowed()) {
                // no beta mode no external clear cache allowed
                $response = new Response('', Codes::HTTP_FORBIDDEN);
                $event->setResponse($response);

                return;
            }

            if (!$this->api->isClearCacheRequestValid($request)) {
                $response = new Response('Invalid sign', Codes::HTTP_FORBIDDEN);
                $event->setResponse($response);

                return;
            }
        }
    }
}
