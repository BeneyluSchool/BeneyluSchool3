<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Utils\Crypt;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LogoutController extends Controller
{
    /**
     * @Route("/disconnect", name="disconnect_user", options={ "expose": true })
     */
    public function disconnectAction(Request $request)
    {
        $clientId = explode('_', $this->container->getParameter('oauth_security_client_id'));
        if (is_array($clientId)) {
            $clientId = reset($clientId);
        } else {
            $clientId = null;
        }
        $parameters = array(
            'referer'           => $request->getHost(),
            'client_id_referer' => $clientId,
        );
        $redirect = $request->get('redirect');

        $user = $this->getUser();
        if ($user && $user instanceof User) {
            // redirect ARGOS users back to ARGOS on logout
            if ($this->container->hasParameter('argos_url')
                && $this->container->hasParameter('argos_academy')
                && $this->container->getParameter('argos_academy') === $user->getAafAcademy()
            ) {
                $redirect = $this->container->getParameter('argos_url');
            }
            $secretKey = $this->getParameter('security.logout.username.secret_key');
            $parameters['username'] = Crypt::encrypt($user->getLogin(), $secretKey);

            $this->get('bns.analytics.manager')->track('LOGGED_OUT_USER', $user);

            //On vide le cache Redis à la déconnexion
            $this->get('bns.user_manager')->setUser($user)->resetRights();
            // logout user
            $this->get('bns_common.security_logout.logout')->logout();
        }

        if ($redirect) {
            $parameters['logout_referer_override'] = $redirect;
        }

        return $this->redirect($this->getParameter('auth_url') . $this->getParameter('auth_logout_route') . '?' . http_build_query($parameters));
    }
}
