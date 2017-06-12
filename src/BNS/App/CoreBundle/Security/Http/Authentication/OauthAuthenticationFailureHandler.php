<?php
namespace BNS\App\CoreBundle\Security\Http\Authentication;

use FOS\RestBundle\Util\Codes;
use HWI\Bundle\OAuthBundle\Security\OAuthUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\UrlValidator;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class OauthAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    protected $httpUtils;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var OAuthUtils
     */
    protected $oAuthUtils;

    protected $validator;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var  TranslatorInterface */
    protected $translator;

    public function __construct(HttpUtils $httpUtils, OAuthUtils $OAuthUtils, RouterInterface $router, ValidatorInterface $validator, LoggerInterface $logger = null, TranslatorInterface $translator = null)
    {
        $this->logger = $logger;
        $this->httpUtils = $httpUtils;
        $this->oAuthUtils = $OAuthUtils;
        $this->router = $router;
        $this->validator = $validator;
        $this->translator = $translator;
    }

    /**
     * This is called when an interactive authentication attempt fails. This is
     * called by authentication listeners inheriting from
     * AbstractAuthenticationListener.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return Response The response to return, never null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $error = $request->get('error');
        $errorDescription = $request->get('error_description');
        $errorUri = $request->get('error_uri');

        if ($exception instanceof LockedException) {
            $error = 'locked_account';
        }

        switch ($error) {
            case 'access_denied':
                // User denied the access or can't connect
                $errorMessage = 'oauth_invalid_access';
                if ($errorDescription) {
                    // special case redirect user to the right place
                    $urlConstraint = new Url();
                    $errors = $this->validator->validateValue($errorDescription, $urlConstraint);
                    if (!count($errors)) {
                        $errorMessage = 'oauth_invalid_access_redirect_uri';
                        $request->getSession()->set('bns.oauth_error_redirect_uri', $errorDescription);
                    }
                }
                break;
            case 'locked_account';
                if ($this->logger) {
                    $this->logger->error('OAuth login attempt with locked user');
                }
                $redirectUrl = $this->oAuthUtils->getAuthorizationUrl($request, 'bns_auth_provider');

                return new RedirectResponse($this->router->generate('disconnect_user', array(
                    'redirect' => $redirectUrl,
                )));
            default:
            case 'invalid_request':
            case 'unauthorized_client':
            case 'unsupported_response_type':
            case 'invalid_scope':
                $errorMessage = 'oauth_configuration_error';
                break;
            case 'server_error':
            case 'temporarily_unavailable':
                $errorMessage = 'oauth_temporary_error';
                break;
        }
        $request->getSession()->set('bns.oauth_error_message', $errorMessage);

        if ($this->logger) {
            $this->logger->error(sprintf('OAuth authorization error: %s', $error), array('error_description' => $errorDescription, 'errorUri' => $errorUri, 'Exception' => $exception->getMessage()));
        }

        if ($request->isXmlHttpRequest() || $request->get('_xhr_call', false)) {
            if ($this->translator) {
                $errorMessage = /** @Ignore */$this->translator->trans('main.oauth.error.' . $errorMessage, [], 'MAIN');
            }

            return new JsonResponse([
                'error' => $error,
                'message' => $errorMessage
            ], Codes::HTTP_UNAUTHORIZED);
        }

        return $this->httpUtils->createRedirectResponse($request, 'main_oauth_error');
    }

}
