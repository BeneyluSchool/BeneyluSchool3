<?php

namespace BNS\App\CoreBundle\Security\Http\Authentication;

use BNS\App\CoreBundle\Model\Logging;

use FOS\RestBundle\Util\Codes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoggingAuthenticationSuccessHandler extends DefaultAuthenticationSuccessHandler
{
    /**
     * Constructor.
     *
     * @param HttpUtils $httpUtils
     * @param array $options Options for processing a successful authentication attempt.
     */
    public function __construct(HttpUtils $httpUtils, array $options)
    {
        parent::__construct($httpUtils, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $successLogging = new Logging();
        $successLogging->setUserId($token->getUser()->getId());
        $successLogging->setUsername($token->getUser()->getLogin());
        $successLogging->setRoute('connexion_handler');
        $successLogging->setAction('success');
        $successLogging->save();

        if (null !== $this->providerKey && $targetUrl = $request->getSession()->get('_security.'.$this->providerKey.'.target_path')) {
            if (preg_match('/\.json$/', $targetUrl)) {
                $request->getSession()->remove('_security.'.$this->providerKey.'.target_path');
                $request->getSession()->set('need_refresh', true);
            }
        }

        $response = parent::onAuthenticationSuccess($request, $token);

        if ($request->isXmlHttpRequest() || $request->get('_xhr_call', false)) {
            $response = new JsonResponse([
                'redirect_url' => $response->getTargetUrl()
            ], Codes::HTTP_OK);
        }

        return $response;
    }
}
