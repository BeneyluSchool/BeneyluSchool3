<?php

namespace BNS\App\UserBundle\Credentials;

use Guzzle\Http\ClientInterface;
use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth2ResourceOwner;
use HWI\Bundle\OAuthBundle\Security\Core\Authentication\Token\OAuthToken;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class UserCredentialsManager
 *
 * @package BNS\App\UserBundle\Credentials
 */
class UserCredentialsManager
{

    const NEED_UPDATE_CREDENTIAL_SESSION_KEY = 'need_update_credential';

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var GenericOAuth2ResourceOwner
     */
    protected $authProvider;

    /**
     * Cache of the current user information
     *
     * @var array
     */
    protected $userInfo;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ClientInterface $client,
     * @param TokenStorageInterface $tokenStorage,
     * @param GenericOAuth2ResourceOwner $authProvider
     * @param TranslatorInterface $translator
     */
    public function __construct(
        ClientInterface $client,
        TokenStorageInterface $tokenStorage,
        GenericOAuth2ResourceOwner $authProvider,
        TranslatorInterface $translator,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->tokenStorage = $tokenStorage;
        $this->authProvider = $authProvider;
        $this->translator = $translator;
        $this->logger = $logger;
    }

    /**
     * Checks whether the current user credentials have expired.
     *
     * @return bool
     */
    public function haveCredentialsExpired()
    {
        $user = $this->getUserInfo();

        return isset($user['credentials_expired']) && $user['credentials_expired'];
    }

    /**
     * Updates the user password
     *
     * @param array $data
     * @return array The new user credentials, or form errors if any
     */
    public function updatePassword($data)
    {
        if (isset($data['new_password'])) {
            $data['plain_password'] = $data['new_password'];
            unset($data['new_password']);
        }

        return $this->send('post', '/oauth/v2/users/password', [
            'form' => $data,
        ]);
    }


    /**
     * Gets the current user info from Auth. Results are cached and reused by
     * later calls.
     *
     * @param bool $refresh Whether to refresh cached data
     * @return array
     */
    protected function getUserInfo($refresh = false)
    {
        if (!$this->userInfo || $refresh) {
            $this->userInfo = $this->send('get', '/oauth/v2/users');
        }

        return $this->userInfo;
    }

    /**
     * Executes an API request
     *
     * @param string $method The used method: 'get', 'post', ...
     * @param string $url API url
     * @param array $params Url parameters
     * @return array The API response
     * @throws \ErrorException
     */
    protected function send($method, $url, array $params = [])
    {
        $method = strtolower($method);
        if (!in_array($method, ['get', 'post', 'patch'])) {
            throw new \InvalidArgumentException('Unsupported method');
        }

        // ensure we have a fresh token
        $token = $this->tokenStorage->getToken();
        $accessToken = null;
        if ($token && $token instanceof OAuthToken) {
            if ($token->isExpired()) {
                $rawToken = $this->authProvider->refreshAccessToken($token->getRefreshToken());
                $token->setRawToken($rawToken);
                $this->tokenStorage->setToken($token);
            }
            $accessToken = $token->getAccessToken();
        }
        if (!$accessToken) {
            $this->logger->debug('UserCredentialsManager no Access Token available', [
                'token' => $token
            ]);
            throw new AccessDeniedException();
        }

        $params = array_merge([
            'access_token' => $accessToken,
            '_locale' => $this->translator->getLocale()
        ], $params);

        switch ($method) {
            case 'get':
                $request = $this->client->get($url.'?'.http_build_query($params));
                break;
            default:
                $request = $this->client->$method($url, null, $params);
        }

        $response = $request->send();
        if (!$response->isSuccessful()) {
            throw new \ErrorException('Something went wrong');
        }

        return $response->json();
    }

}
