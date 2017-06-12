<?php

namespace BNS\App\PortalBundle\Controller;

use BNS\App\CoreBundle\Model\User;
use BNS\App\PortalBundle\Model\PortalQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

class OutController extends CommonController
{
    /**
     * @Route("/{slug}", name="BNSAppPortalBundle_homepage")
     * @Template()
     */
    public function homepageAction($slug, Request $request)
    {
        $user = $this->getUser();
        $session = $this->get('session');

        $portal = PortalQuery::create()->findOneBySlug($slug);
        //Si on ne trouve pas de portail on redirige vers la home
        if (!$portal) {
            return $this->redirect($this->generateUrl('home'));
        }

        // On soumet un refresh lorsque la centrale sera de retour sur l'app
        if (!$user || !$user instanceof User) {
            $session->set('need_refresh', true);

            // On transfert la target_path native de Symfony vers une custom, pour éviter la redirection dans l'IFrame.
            if ($session->has('_security.oauth_area.target_path')) {
                $session->set('_bns.target_path', $session->get('_security.oauth_area.target_path'));
                $session->remove('_security.oauth_area.target_path');
            }
            
            $locale = $this->container->hasParameter('bns_force_login_local') ? $this->getParameter('bns_force_login_local') : $request->getLocale();

            return array(
                'isAuthenticated' => false,
                'redirectUrl' =>  $this->get('hwi_oauth.security.oauth_utils')->getAuthorizationUrl($request, 'bns_auth_provider', null, ['_locale' => $locale]),
                'portal' => $portal,
                'simplePie' => $this->get('fkr_simple_pie.rss'),
                'gm' => $this->get('bns.group_manager')->setGroup($portal->getGroup())
            );

        }
        // IS_AUTHENTICATED (loggé)
        elseif ($session->get('need_refresh', false)) {
            $session->remove('need_refresh');
            // Launch all checks
            $redirect = $this->get('bns.user_manager')->onLogon();
            $this->get('bns.analytics.manager')->trackFullUserLogin(
                $user,
                $this->get('bns.right_manager')->getCurrentGroup(),
                $session
            );

            return $this->render('BNSAppMainBundle:Logon:refresh.html.twig', array('redirect' => $redirect));
        }
        // Nous redirigeons selon la page accessible
        return $this->redirect($this->generateUrl($this->get('bns.right_manager')->getRedirectLoginRoute()));
    }
}
