<?php
namespace BNS\App\CoreBundle\Security\Http\EntryPoint;

use FOS\RestBundle\Util\Codes;
use HWI\Bundle\OAuthBundle\Security\Http\EntryPoint\OAuthEntryPoint;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApiAuthenticationEntryPoint  extends OAuthEntryPoint
{
    /**
     * Starts the authentication scheme.
     *
     * @param Request $request The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return Response
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if (preg_match('/^\/api/', $request->getPathInfo())) {
            return new JsonResponse('invalid credentials ' . $request->getPathInfo(), Codes::HTTP_UNAUTHORIZED);
        }

        return parent::start($request, $authException);
    }
}
