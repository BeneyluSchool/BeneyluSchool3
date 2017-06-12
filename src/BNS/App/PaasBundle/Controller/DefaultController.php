<?php

namespace BNS\App\PaasBundle\Controller;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use FOS\RestBundle\Util\Codes;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DefaultController extends Controller
{



    protected function getClientFromRequest(Request $request)
    {
        return $this->get('bns.paas_manager')->getClient($request->get('clientType'), $request->get('clientIdentifier'));
    }

    /**
     * Le Paas nous indique que nous devons mettre à jour nos abonnements
     * @Route("/proceed/{type}/{identifier}",name="BNSAppPaasBundle_proceed_subscriptions")
     */
    public function proceedSubscriptionsAction(Request $request, $type, $identifier)
    {
        $this->checkRequest($request, $type, $identifier);
        $this->get('bns.paas_manager')->resetClient($this->getClientFromRequest($request));

        return new Response('OK');
    }

    /**
     *
     */
    public function getSubscriptionsAction()
    {

    }

    /**
     * Le Paas nous demande si on connaît le client
     * @Route("/checkClient", name="BNSAppPaasBundle_check_client")
     */
    public function checkClient(Request $request)
    {
        $this->get('bns.paas_manager')->checkRequest($request);
        $client = $this->getClientFromRequest($request);

        if ($client) {
            $data = array();
            $data['client_identifier'] = $client->getPaasIdentifier();
            if ($client instanceof Group) {
                $data['client_name'] = $client->getLabel();
                $data['client_uai'] = $client->getAttribute('UAI');
            } elseif ($client instanceof User) {
                $data['client_name'] = $client->getFullName();
            }
            if ($client->getLang()) {
                $data['client_language'] = $client->getLang();
            }

            return new JsonResponse($data);
        }

        return new Response('', Codes::HTTP_NOT_FOUND);
    }

    /**
     * Le Paas nous demande si on connaît le client
     * @Route("/resetClient", name="BNSAppPaasBundle_reset_client")
     */
    public function resetClient(Request $request)
    {
        $this->get('bns.paas_manager')->checkRequest($request);
        $client = $this->getClientFromRequest($request);
        $this->get('bns.paas_manager')->resetClient($client);

        return new Response('Retour ENT RESET', 200);
    }


}
