<?php

namespace BNS\App\PaasBundle\Manager;

use BNS\App\CoreBundle\Buzz\Browser;
use BNS\App\CoreBundle\Model\User;
use BNS\App\PaasBundle\Client\PaasClientInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class PaasSubscriptionManager
{

    protected $expires = 86400;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $paasUrl;

    /**
     * @var string
     */
    protected $subscriptionsUrl;

    /** @var Browser $buzz */
    protected $buzz;

    /**
     * @var PaasSecurityManager
     */
    protected $paasSecurityManager;

    public function __construct(PaasSecurityManager $paasSecurityManager, $buzz, $redis, $paasUrl)
    {
        $this->paasSecurityManager = $paasSecurityManager;
        $this->buzz = $buzz;
        $this->redis = $redis;
        $this->paasUrl = $paasUrl;
        $this->subscriptionsUrl = $paasUrl . '/api/subscriptions/by-identifier/%identifier%/%type%.json';
    }

    /**
     * @param PaasClientInterface $client
     * @param bool $clearCache
     * @return array
     */
    public function getCurrentSubscriptions(PaasClientInterface $client, $clearCache = false)
    {
        $key = self::getClientCacheKey($client, 'cur_sub');
        if ($clearCache || !($cache = $this->redis->get($key))) {
            $url = str_replace(['%identifier%', '%type%'], [$client->getPaasIdentifier(), $client->getPaasType()], $this->subscriptionsUrl);
            $signedUrl = $this->paasSecurityManager->signUrl('GET', $url);
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->get($signedUrl);

            if ($response->isSuccessful()) {
                $content = $response->getContent();
                if (null !== ($data = json_decode($content, true))) {
                    if (is_array($data) && isset($data['subscriptions'])) {
                        // store json_encoded into cache
                        $this->redis->set($key, json_encode($data['subscriptions']), 'EX', $this->expires);

                        return $data['subscriptions'];
                    }
                }
            }

            // TODO handle exceptions
            return array();
        }

        return json_decode($cache, true);
    }

    public function getLicences(PaasClientInterface $client, $clearCache = false)
    {
        $licences = [];
        foreach ($this->getCurrentSubscriptions($client, $clearCache) as $subscription) {
            if (isset($subscription['offer']['type']) && $subscription['offer']['type'] === 'LICENCE') {
                $begin = new \DateTime($subscription['begin']);
                $end = new \DateTime(isset($subscription['end']) ? $subscription['end'] : 'now');
                $now = new \DateTime();

                if ($begin < $now && (true === $subscription['life_time'] || $end > $now)) {
                    $licences[] = [
                        'licence'   => strtoupper($subscription['offer']['unique_name']),
                        'life_time' => isset($subscription['life_time']) ? (bool)$subscription['life_time'] : false,
                        'end'       => $end,
                    ];
                }
            }
        }

        return $licences;
    }

    public function resetCache(PaasClientInterface $client)
    {
        $key = static::getClientCacheKey($client, 'cur_sub');
        $this->redis->del($key);
    }

    public static function getClientCacheKey(PaasClientInterface $client, $type)
    {
        $clientType = 'group';
        if ($client instanceof User) {
            $clientType = 'user';
        }

        return 'paas_' . $type . '_' . $clientType . '_' . $client->getPaasIdentifier();
    }
}
