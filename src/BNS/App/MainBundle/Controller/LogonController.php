<?php

namespace BNS\App\MainBundle\Controller;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\MainBundle\Model\HomeNewQuery;
use BNS\App\PortalBundle\Model\PortalQuery;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class LogonController extends Controller
{
    /**
     * @Route("/{_locale}/", requirements={"_locale":"fr|en|en_US|en_GB|es|es_AR|"}, name="home_locale")
     * @Route("/", name="home", options={"expose": true})
     */
    public function indexAction(Request $request)
    {
        $user = $this->getUser();

        $session = $this->get('session');

        // On soumet un refresh lorsque la centrale sera de retour sur l'app
        if (!$user || !$user instanceof User) {
            if ($this->container->hasParameter('bns_default_portal') && $portalSlug = $this->getParameter('bns_default_portal')) {
                $portal = PortalQuery::create()->filterBySlug($portalSlug)->findOne();
                if ($portal) {
                    return $this->redirect($this->generateUrl('BNSAppPortalBundle_homepage', ['slug' => $portal->getSlug()]));
                }
            }

            if ($this->container->hasParameter('bns_force_login_local')) {
                $locale = $this->getParameter('bns_force_login_local');
                $request->setLocale($locale);
                $session->set('_locale', $locale);
                $this->container->get('translator')->setLocale($locale);
            }

            $session->set('need_refresh', true);

            // On transfert la target_path native de Symfony vers une custom, pour éviter la redirection dans l'IFrame.
            if ($session->has('_security.oauth_area.target_path')) {
                $session->set('_bns.target_path', $session->get('_security.oauth_area.target_path'));
                $session->remove('_security.oauth_area.target_path');
            }

            if ($this->container->hasParameter('bns.ng.enable_login') && $this->container->getParameter('bns.ng.enable_login')) {
                return $this->redirect($this->generateUrl('ng_login'));
            }

            $logonView = 'BNSAppMainBundle:Logon:index.html.twig';
            if ($this->container->hasParameter('logon.custom_view')) {
                $logonView = $this->container->getParameter('logon.custom_view');
            }

            $authoriseIframe = $this->container->getParameter('security.oauth.client_id') != "undefined";

            //Définition des paramètres de page d'accueil à partir du fichier de domaine
            if ($this->container->hasParameter('logon.custom_view.params')
                && is_array($this->container->getParameter('logon.custom_view.params'))
            ) {
                $viewParams = $this->container->getParameter('logon.custom_view.params');
                if (isset($viewParams['group_id'])) {
                    //Nous avons affaire à une page ayant des contenu à remonter, c'est parti !
                    $viewParams['news'] = HomeNewQuery::create()
                        ->filterByGroupId($viewParams['group_id'])
                        ->orderByCreatedAt(\Criteria::DESC)
                        ->limit(3)
                        ->find();
                    if (isset($viewParams['parent_id'])) {
                        $viewParams['parentNews'] = HomeNewQuery::create()
                            ->filterByGroupId($viewParams['parent_id'])
                            ->orderByCreatedAt(\Criteria::DESC)
                            ->limit(3)
                            ->find();
                        $viewParams['parentName'] = $viewParams['parent_reference_name'];
                    }
                    $gm = $this->get('bns.group_manager');
                    $gm->setGroupById($viewParams['group_id']);
                    $viewParams['hasAlert'] = $gm->getAttribute('HOME_ACTIVE_ALERT');
                    if ($viewParams['hasAlert']) {
                        $viewParams['alertTitle'] = $gm->getAttribute('HOME_ALERT_TITLE');
                        $viewParams['alertContent'] = $gm->getAttribute('HOME_ALERT');
                    }
                }
            } else {
                $viewParams = null;
            }

            $needAnalyticsLogout = true;
            if (strpos($request->headers->get('referer'), 'disconnect')) {
                $needAnalyticsLogout = true;
            }

            if (array_key_exists($request->getLocale(), $this->getParameter('bns_homepage_links'))) {
                $homeLink = array_merge($this->getParameter('bns_homepage_links')['en'], $this->getParameter('bns_homepage_links')[$request->getLocale()]);
            } else {
                $homeLink = $this->getParameter('bns_homepage_links')['en'];
            }
            $locale = $this->container->hasParameter('bns_force_login_local') ? $this->getParameter('bns_force_login_local') : $request->getLocale();

            if ($this->container->hasParameter('bns.home.has_announcements') && $this->getParameter('bns.home.has_announcements')) {
                $announcements = $this->get('bns.announcement_manager')->getHomeAnnouncements();
            } else {
                $announcements = [];
            }

            return $this->render($logonView, array(
                'authoriseIframe' => $authoriseIframe,
                'redirectUrl' => $this->get('hwi_oauth.security.oauth_utils')->getAuthorizationUrl($request, 'bns_auth_provider', null, ['_locale' => $locale]),
                'enable_legals' => $this->container->hasParameter('bns.enable_legals') ? $this->container->getParameter('bns.enable_legals') : true,
                'enable_register' => $this->container->hasParameter('bns.enable_register') ? $this->container->getParameter('bns.enable_register') : true,
                'viewParams' => $viewParams,
                'needAnalyticsLogout' => $needAnalyticsLogout,
                'availableLanguages' => $this->get('bns.locale_manager')->getNiceAvailableLanguages(),
                'announcements' => $announcements,
                'home_link'=> $homeLink
            ));

        } else {
            if ($request->get('_need_refresh')) {
                $session->set('need_refresh', true);
            }

            // catch login Error from spot
            if ('spotOauthLoginFailure' === $request->get('error')) {
                $session->getFlashBag()->add('toast-error', $this->get('translator')->trans('ERROR_APPEND_WHEN_LOGIN_TO_SPOT', [], 'MAIN'));
            }

            // catch beta login with wrong user
            $userId = (int)$request->get('user_id');
            if ($userId && ((int)$user->getId() !== $userId)) {
                // logout user
                $this->get('bns_common.security_logout.logout')->logout();

                // redirect to home page
                return $this->redirect($this->generateUrl('home'));
            }

            // IS_AUTHENTICATED (loggé)
            if ($session->get('need_refresh', false)) {
                $session->remove('need_refresh');
                // Launch all checks
                $redirect = $this->get('bns.user_manager')->onLogon();
                $this->get('bns.analytics.manager')->trackFullUserLogin(
                    $user,
                    $this->get('bns.right_manager')->getCurrentGroup(),
                    $session
                );

                if ($request->get('_direct_call')) {
                    return $this->redirect($redirect ? : $this->generateUrl($this->get('bns.right_manager')->getRedirectLoginRoute()));
                }

                return $this->render('BNSAppMainBundle:Logon:refresh.html.twig', array('redirect' => $redirect));
            }
            // redirect to the right place from spot
            if (($clientIdentifier = $request->get('clientIdentifier')) && ($clientType = $request->get('clientType'))) {
                $redirect = $this->get('bns.paas_manager')->getRedirectClient($clientType, $clientIdentifier, $request->get('offerCode'));
                if ($redirect instanceof Response) {
                    return $redirect;
                }
                if (true === $redirect) {
                    $session->set('open_applications_modal', true);
                }
            }

            return $this->redirect($this->generateUrl($this->get('bns.right_manager')->getRedirectLoginRoute()));
        }
    }

    /**
     * @Route("/enter", name="enter")
     * @Template()
     *
     * @deprecated this should be removed and not used anymore
     */
    public function enterAction()
    {
        $user = $this->getUser();
        if ($user && $user instanceof User) {
            return array('returnUrl' => $this->generateUrl('home'));
        }

        $parameters = array(
            'response_type' => 'code',
            'client_id' => $this->container->getParameter('security.oauth.client_id'),
            'redirect_uri' => $this->generateUrl('_security_login_check', array(), true)
        );

        return $this->redirect($this->container->getParameter('oauth_host') . $this->container->getParameter('security.oauth.authorization_url') . '?' . http_build_query($parameters));
    }

    /**
     * Page des mentions légales
     * @Route("/mentions-legales", name="main_logon_legals")
     * @Template()
     */
    public function legalsAction()
    {
        if ($this->container->hasParameter('bns.enable_legals') && !$this->container->getParameter('bns.enable_legals')) {
            return $this->redirect($this->generateUrl('home'));
        }

        return array(
            'enable_legals' => $this->container->hasParameter('bns.enable_legals') ? $this->container->getParameter('bns.enable_legals') : true,
            'client_legals' => $this->container->hasParameter('bns.client_legals') ? $this->container->getParameter('bns.client_legals') : 'base'
        );
    }

    /**
     * Page des mentions sur les cookies
     * @Route("/cookies", name="main_logon_cookies")
     * @Template()
     */
    public function cookiesAction()
    {
        return array();
    }

    /**
     * Action de cachage du message d'alert
     * @Route("/cacher-alerte", name="main_logon_hide_alert", options={"expose"=true})
     */
    public function hideAlertAction()
    {
        //Fin au 5 mars pour première vague
        $cookie = new Cookie('hide-alert', 'true', time() > 1394015219 ? 1396690019 : 1394015219);
        $response = new Response();
        $response->headers->setCookie($cookie);
        $response->setContent($this->container->get('translator')->trans('ALERT_OK', array(), 'MAIN'));
        return $response->send();
    }

    /**
     * Url d'autoconnexion à l'issue du processus d'inscription 'simple email'
     * @Route("/connect-from-key/{token}", name="main_logon_autoconnect")
     */
    public function autoconnectAction($token, Request $request)
    {
        if ($token == null || $token == '') {
            throw new NotFoundHttpException('no token');
        }
        $user = UserQuery::create()->findOneByConnexionToken($token);
        if (!$user) {
            return new Response('no user', 404);
        }

        $user->resetConnexionToken();

        $token = new UsernamePasswordToken($user, null, "oauth_area", $user->getRoles());
        //$this->get("security.context")->setToken($token); //now the user is logged in
        $this->get('security.token_storage')->setToken($token);
        //now dispatch the login event
        $request = $this->get("request");
        $event = new InteractiveLoginEvent($request, $token);
        $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);

        return $this->redirect($this->generateUrl('home'));

    }

    /**
     * @Route("/activation-de-compte")
     * @Template()
     */
    public function activateAction(Request $request)
    {
        $templateVars = [];
        $form = $this->createFormBuilder()
            ->add('email', 'email', [
                'constraints' => [
                    new NotBlank(),
                    new Email(),
                ],
            ])
            ->getForm()
        ;
        $form->handleRequest($request);
        if ($form->isValid()) {
            $email = $form->getData()['email'];
            /** @var User $user */
            $user = null;
            /** @var \PropelObjectCollection|User[] $users */
            $users = UserQuery::create()
                ->filterByEmail($email)
                ->filterByArchived(false)
                ->filterByAafId(null, \Criteria::ISNOTNULL)
                ->lastCreatedFirst()
                ->find()
            ;
            if (1 === $users->count()) {
                $user = $users->getFirst();
            } elseif (1 < $users->count()) {
                // Multiple matches. Keep the first that has AAF info.
                foreach ($users as $u) {
                    if ($u->getAafId()) {
                        $user = $u;
                        break;
                    }
                }
                // still not found, resort to last created user
                if (!$user) {
                    $user = $users->getFirst();
                }
            }

            if ($user && $this->get('bns.user_manager')->setUser($user)->hasEnabledSchool()) {
                if ($user->getAccountRequested()) {
                    $templateVars['already_requested'] = true;
                    $templateVars['reset_password_url'] = $this->generateUrl('user_password_reset', [], true);
                } else {
                    $user->setAccountRequested(true)->save();
                    $this->get('bns.user_manager')->resetUserPassword($user, false);
                    $this->get('bns.mailer')->sendUser('ACTIVATE_ACCOUNT_SUCCESS', array(
                        'first_name'		=> $user->getFirstName(),
                        'login'				=> $user->getLogin(),
                        'plain_password'	=> $user->getPassword()
                    ), $user);

                    return $this->redirect($this->generateUrl('bns_app_main_logon_activationconfirmation'));
                }
            } else {
                $templateVars['not_found'] = true;
            }
        }

        $templateVars['form'] = $form->createView();

        return $templateVars;
    }

    /**
     * @Route("/activation-de-compte/confirmation")
     * @Template()
     */
    public function activationConfirmationAction()
    {
        return [];
    }

    /**
     * @Route("/goto/{app}/{idp}", name="main_go_to_app")
     */
    public function redirectToApplicationAction(Request $request, $app, $idp = null)
    {
        $application = $this->get('bns_core.application_manager')->getApplication($app);
        if ($application) {
            $user = $this->getUser();
            if (!$user || !$user instanceof User) {
                // not connected
                // validate idp
                if ($idp && !preg_match('/^[a-z0-9-_]+$/i', $idp)) {
                    throw $this->createNotFoundException('invalid idp');
                }

                // override return url
                $session = $request->getSession();
                $session->set('need_refresh', true);
                $session->set('_bns.target_path', $this->generateUrl('main_go_to_app', ['app' => $application->getUniqueName(), 'idp' => $idp]));
                $session->remove('_security.oauth_area.target_path');

                $params = [
                    '_locale' => $this->container->hasParameter('bns_force_login_local') ? $this->getParameter('bns_force_login_local') : $request->getLocale(),
                ];
                if ($idp) {
                    $params['_samlidp'] = $idp;
                }

                return $this->redirect($this->get('hwi_oauth.security.oauth_utils')->getAuthorizationUrl($request, 'bns_auth_provider', null, $params));
            }

            $rightManager = $this->get('bns.right_manager');
            $right = $application->getUniqueName() . '_ACCESS';
            $router = $this->get('router');
            if (
                $application->hasRouteFront($router) &&
                ($frontRoute = $application->getRouteFront()) &&
                $rightManager->hasRight($right)
            ) {
                return $this->redirect($this->generateUrl($frontRoute));
            } elseif (
                $application->hasRouteBack($router) &&
                ($backRoute = $application->getRouteBack()) &&
                $rightManager->hasRight($right . '_BACK')
            ) {
                return $this->redirect($this->generateUrl($backRoute));
            }
        }

        return $this->redirect($this->generateUrl('home'));
    }
}
