<?php

namespace BNS\App\RealtimeBundle\Publisher;

use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Predis\Client;

/**
 * Class RealtimePublisher
 *
 * @package BNS\App\RealtimeBundle\Publisher
 */
class RealtimePublisher
{

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Client
     */
    private $redis;

    public function __construct(SerializerInterface $serializer, Client $redis)
    {
        $this->serializer = $serializer;
        $this->redis = $redis;
    }

    /**
     * Publishes the given data to a channel, using redis messaging and optional serialization groups
     *
     * @param string $channel
     * @param mixed $data
     * @param array $groups
     */
    public function publish($channel, $data = null, $groups = array())
    {
        if (is_object($data) || is_array($data)) {
            $context = SerializationContext::create();
            if (count($groups)) {
                $context->setGroups($groups);
            }
            $data = $this->serializer->serialize($data, 'json', $context);
        }

        $this->redis->publish($channel, $data);
    }

}
