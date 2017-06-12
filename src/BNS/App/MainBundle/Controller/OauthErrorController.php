<?php
namespace BNS\App\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class OauthErrorController extends Controller
{
    /**
     * @Route("/oauth-error", name="main_oauth_error")
     * @Template()
     * @return array
     */
    public function errorAction()
    {
        $session = $this->get('session');

        $message = $session->get('bns.oauth_error_message');
        $redirectUri  = $session->get('bns.oauth_error_redirect_uri', false);
        $session->remove('bns.oauth_error_message');
        $session->remove('bns.oauth_error_redirect_uri');

        if (!$message) {
            return array(
                'redirectHome' => true
            );
        }

        $errorMessage = 'main.oauth.error.' . $message;
        $retry = ($message !== 'oauth_invalid_access');

        return array(
            'redirectHome' => false,
            'errorMessage' => $errorMessage,
            'redirectUri'  => $redirectUri,
            'retry'        => $retry
        );
    }
}
