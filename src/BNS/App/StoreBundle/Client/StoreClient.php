<?php

namespace BNS\App\StoreBundle\Client;

use BNS\App\StoreBundle\Client\Message\Request;
use Buzz\Browser;
use Buzz\Message\RequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Route;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class StoreClient
{
    /**
     * @var Browser
     */
    private $buzz;

    /**
     * @var \Snc\RedisBundle\Client\Phpredis\Client
     */
    private $cache;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContext
     */
    private $securityContext;

    /**
     * @var \BNS\App\CoreBundle\User\BNSUserManager
     */
    private $userManager;

    /**
     * @var \BNS\App\CoreBundle\Right\BNSRightManager
     */
    private $rightManager;

    /**
     * @var int
     */
    private $userKey;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $version;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     *
     * @param ContainerInterface $cache
     * @param int                $userKey
     * @param string             $apiKey
     * @param string             $version
     * @param string             $baseUrl
     */
    public function __construct(ContainerInterface $container, $userKey, $apiKey, $version, $baseUrl)
    {
        $this->buzz            = $container->get('buzz');
        $this->cache           = $container->get('snc_redis.default');
        $this->securityContext = $container->get('security.context');
        $this->userManager     = $container->get('bns.user_manager');
        $this->rightManager    = $container->get('bns.right_manager');
        $this->userKey         = $userKey;
        $this->apiKey          = $apiKey;
        $this->version         = $version;
        $this->baseUrl         = $baseUrl;
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
    public function post($uri, array $queries = array(), array $parameters = array())
    {
        return $this->createRequest($uri, RequestInterface::METHOD_POST, $queries, $parameters);
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
    public function get($uri, array $queries = array())
    {
        return $this->createRequest($uri, RequestInterface::METHOD_GET, $queries);
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
    public function patch($uri, array $queries = array(), array $parameters = array())
    {
        return $this->createRequest($uri, RequestInterface::METHOD_PATCH, $queries, $parameters);
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
    public function put($uri, array $queries = array(), array $parameters = array())
    {
        return $this->createRequest($uri, RequestInterface::METHOD_PUT, $queries, $parameters);
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Response
     */
    public function delete($uri, array $queries = array(), array $parameters = array())
    {
        return $this->createRequest($uri, RequestInterface::METHOD_DELETE, $queries, $parameters);
    }

    /**
     * @param \BNS\App\StoreBundle\Client\Message\Request $request
     *
     * @return Message\Response
     */
    public function send(Request $request)
    {
        // Replace query parameters in the URI
        $uri = $this->getUri($request->getUri(), $request->getQueries());

        // Retrieve from cache is allowed
        if ($request->isUseCache()) {
            $cache = $this->cache->get('store:' . $uri);
            if (null !== $cache) {
                return unserialize($cache);
            }
        }

        $user      = $this->securityContext->getToken()->getUser();
        $signature = $this->getSignature($uri);
        $fullUri   = $this->baseUrl . $uri .  '?' . http_build_query(array(
            'timestamp' => date('U'),
            /*'user_data' => array(
                'id'        => $user->getLogin(),
                'classroom' => $this->rightManager->getCurrentGroup()->getLabel(),
                'profile'   => $this->userManager->setUser($user)->getMainRole()
            )*/
        ));
        
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            sprintf('Authorization: BNS-STORE %s:%s', $this->userKey, $signature)
        );
        
        switch ($request->getMethod()) {
            case RequestInterface::METHOD_POST:
                $response = $this->buzz->post($fullUri, $headers, json_encode($request->getParameters()));
            break;

            case RequestInterface::METHOD_PUT:
                $response = $this->buzz->put($fullUri, $headers, json_encode($request->getParameters()));
            break;

            case RequestInterface::METHOD_DELETE:
                $response = $this->buzz->delete($fullUri, $headers, json_encode($request->getParameters()));
            break;

            case RequestInterface::METHOD_PATCH:
                $response = $this->buzz->patch($fullUri, $headers, json_encode($request->getParameters()));
            break;

            // GET
            default: $response = $this->buzz->get($fullUri, $headers);
        }

        // Throw errors process
        if (!$response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);
            
            throw new $content[0]['class']($content[0]['message']);
        }

        // Store in cache if allowed
        if ($request->isUseCache()) {
            $this->cache->set('store:' . $uri, serialize($response));

            if ($request->hasTimeToLive()) {
                $this->cache->expire('store:' . $uri, $request->getTimeToLive());
            }
        }

        return $response;
    }

    /**
     * @param string $uri
     * @param array  $queries
     */
    public function removeCache($uri, array $queries = array())
    {
        $this->cache->del('store:' . $this->getUri($uri, $queries));
    }

    /**
     * @param string $uri
     * @param array  $queries
     *
     * @return string
     *
     * @throws MissingMandatoryParametersException
     */
    private function getUri($uri, array $queries)
    {
        $finalUri = '';
        $uri      = '/api/{version}' . (0 === strpos($uri, '/') ? '' : '/') . $uri;
        $route    = new Route($uri, array_replace(array(
            'version' => $this->version
        ), $queries));
        $queries  = $route->getDefaults();
        
        foreach (array_reverse($route->compile()->getTokens()) as $token) {
            if ('text' == $token[0]) {
                $finalUri .= $token[1];
            }
            else {
                // variable
                if (!isset($queries[$token[3]])) {
                    throw new MissingMandatoryParametersException('The store client call API has one missing mandatory parameter "' . $token[3] . '" for route "' . $uri . '" !');
                }

                $finalUri .= $token[1] . $queries[$token[3]];
            }
        }

        return $finalUri;
    }

    /**
     * @param string $uri
     * @param array  $queries
     * @param array  $parameters
     *
     * @return \BNS\App\StoreBundle\Client\Message\Request
     */
    protected function createRequest($uri, $method, array $queries, array $parameters = array())
    {
        return new Request($this, $uri, $method, $queries, $parameters);
    }

    /**
     * @param string $uri
     *
     * @return string
     */
    protected function getSignature($uri)
    {
        return hash_hmac('sha1', $uri, $this->apiKey);
    }
}