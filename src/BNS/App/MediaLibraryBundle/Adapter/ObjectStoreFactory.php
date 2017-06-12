<?php

namespace BNS\App\MediaLibraryBundle\Adapter;

use Gaufrette\Adapter\OpenStackCloudFiles\ObjectStoreFactoryInterface;
use OpenCloud\Identity\Resource\Token;
use OpenCloud\OpenStack;
use Psr\Log\LoggerInterface;

/**
 * ObjectStoreFactory
 *
 * @author Daniel Richter <nexyz9@gmail.com>
 */
class ObjectStoreFactory implements ObjectStoreFactoryInterface
{
    /**
     * @var OpenStack
     */
    protected $connection;

    /**
     * @var string
     */
    protected $region;

    /**
     * @var string
     */
    protected $urlType;

    /**
     * @var  string
     */
    protected $name;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * Constructor
     *
     * @param OpenStack $connection
     * @param string $region
     * @param string $urlType
     */
    public function __construct(OpenStack $connection, $name, $region, $urlType,  LoggerInterface $logger = null, $redis = null)
    {
        $this->connection = $connection;
        $this->name = $name;
        $this->region = $region;
        $this->urlType = $urlType;

        $this->logger = $logger;
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectStore()
    {
        $token = $this->connection->getToken();
        if (!$token && $this->redis) {
            $cacheKey = 'openstack_cache.' . $this->name;
            $cache = unserialize($this->redis->get($cacheKey));
            if ($cache) {
                $this->connection->importCredentials($cache);
                if ($this->logger) {
                    $this->logger->debug(sprintf('ObjectStoreFactory::getObjectStore importCredentials from redis cache "%s"', $cacheKey));
                }
            }
            /** @var Token $token */
            $token = $this->connection->getTokenObject();

            if (!$token || ($token && $token->hasExpired())) {
                $this->connection->authenticate();
                $this->redis->set($cacheKey, serialize($this->connection->exportCredentials()));
                $this->redis->expire($cacheKey, 86400); // 24H expiration
                if ($this->logger) {
                    $this->logger->debug(sprintf('ObjectStoreFactory::getObjectStore exportCredentials to redis cache "%s"', $cacheKey));
                }
            }
        }

        return $this->connection->objectStoreService($this->name, $this->region, $this->urlType);
    }
}
