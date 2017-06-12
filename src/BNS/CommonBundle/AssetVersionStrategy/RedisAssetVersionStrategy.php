<?php
namespace BNS\CommonBundle\AssetVersionStrategy;

use Predis\ClientInterface;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class RedisAssetVersionStrategy extends StaticVersionStrategy
{
    private $redis;
    private $redisVersion;

    public function __construct($version, $format = null, ClientInterface $redis = null)
    {
        parent::__construct($version, $format);
        $this->redis = $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function getVersion($path)
    {
        $version = $this->getRedisVersion();

        return $version ?: parent::getVersion($path);
    }

    protected function getRedisVersion()
    {
        if ($this->redis && null === $this->redisVersion) {
            try {
                $this->redisVersion = $this->redis->get('assets_version');
            } catch (\Exception $e) {
                // TODO logme
            }

            $this->redisVersion = $this->redisVersion ?: false;
        }

        return $this->redisVersion;
    }
}
