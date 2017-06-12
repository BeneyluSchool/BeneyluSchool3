<?php

namespace BNS\App\PaasBundle\Manager;

/**
 * Class PaasSecurityManager
 *
 * @package BNS\App\PaasBundle\Manager
 */
class PaasSecurityManager
{

    protected $paasOriginId;
    protected $paasSecretKey;

    public function __construct($paasOriginId, $paasSecretKey)
    {
        $this->paasOriginId = $paasOriginId;
        $this->paasSecretKey = $paasSecretKey;
    }

    /**
     * Sign Url for Paas call (sms api, ...)
     *
     * @param $method
     * @param $url
     * @param array $query
     * @param array $clientIps
     * @return string
     */
    public function signUrl($method, $url, array $query = array(), array $clientIps = [])
    {
        $method = strtoupper($method);
        $path = parse_url($url, PHP_URL_PATH);

        $query['declared_ip'] = implode(',', $clientIps);
        $query['time'] = time();
        $query['origin_id'] = $this->paasOriginId;
        $signatureData = $method . $path;
        $signatureData .= '?' . http_build_query($query);

        $query['key'] = hash_hmac('sha256', $signatureData, $this->paasSecretKey);

        return $url . '?' . http_build_query($query);
    }

    public function getOriginId()
    {
        return $this->paasOriginId;
    }

    public function getSecretKey()
    {
        return $this->paasSecretKey;
    }

}
