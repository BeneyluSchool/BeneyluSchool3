<?php
namespace BNS\App\CoreBundle\Security\Firewall;

use FOS\RestBundle\Util\Codes;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Util\StringUtils;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ApikeyRequestValidator
{
    /** @var  string */
    protected $secretKey;

    /** @var string  */
    protected $env;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var int  */
    protected $timeLimit = 3600;

    public function __construct($secretKey, $env = 'prod', LoggerInterface $logger = null)
    {
        $this->secretKey = $secretKey;
        $this->env = $env;
        $this->logger = $logger;
    }

    /**
     * @return int
     */
    public function getTimeLimit()
    {
        return $this->timeLimit;
    }

    /**
     * @param int $timeLimit
     */
    public function setTimeLimit($timeLimit)
    {
        $this->timeLimit = (int)$timeLimit;
    }

    /**
     * validate a request with an apiKey
     * @param Request $request
     */
    public function validateRequest(Request $request, $secretKey = null)
    {
        if (!$secretKey) {
            $secretKey = $this->secretKey;
        }
        if (!$secretKey) {
            throw new \InvalidArgumentException('a secret key should be provided');
        }
        if (strlen($secretKey) < 16) {
            throw new \InvalidArgumentException('a stronger secret key should be configured');
        }

        $method = $request->getMethod();
        $uri = $request->getBaseUrl() . $request->getPathInfo();
        $params = $request->query->all();

        $time = (int)$request->get('time');
        $key = $request->get('key', $request->headers->get('key'));

        $signatureData = $method . $uri;
        if (abs($time - time()) < $this->timeLimit && $key) {
            unset($params['key']);
            $signatureData .= '?' . http_build_query($params);

            $signature = hash_hmac('sha256', $signatureData, $secretKey);

            // prevent timing attack to guess the secret key
            if (StringUtils::equals($signature, $key)) {
                return;
            }
        }
        // DEV mode allow key 123456
        if (in_array($this->env, ['app_dev', 'dev'], true) && $key == '123456') {
            $this->logger->warning('authenticated with dev keys');

            return;
        }

        throw new AccessDeniedHttpException();
    }
}
