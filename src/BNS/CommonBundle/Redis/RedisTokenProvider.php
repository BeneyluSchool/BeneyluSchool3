<?php

namespace BNS\CommonBundle\Redis;

use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class RedisTokenProvider implements TokenProviderInterface
{
    /** @var  \Redis|\Predis\Client */
    protected $client;

    protected $prefix = 'rbme_token:';

    protected $ttl = 31536000; // 1 year

    public function __construct($client)
    {
        $this->client = $client;
    }

    /**
     * @inheritDoc
     */
    public function loadTokenBySeries($series)
    {
        if ($row = $this->client->hGetAll($this->prefix . $series)) {
            if (isset($row['class']) && isset($row['username']) && isset($row['value'], $row['lastUsed'])) {
                return new PersistentToken($row['class'], $row['username'], $series, $row['value'], new \DateTime('@' . $row['lastUsed']));
            }
        }

        throw new TokenNotFoundException('No token found.');
    }

    /**
     * @inheritDoc
     */
    public function deleteTokenBySeries($series)
    {
        $this->client->del($this->prefix . $series);
    }

    /**
     * @inheritDoc
     */
    public function updateToken($series, $tokenValue, \DateTime $lastUsed)
    {
        /** @var \Redis $pipe */
        $pipe = $this->client->pipeline();
        $pipe->hset($this->prefix . $series, 'value', $tokenValue);
        $pipe->hset($this->prefix . $series, 'lastUsed', $lastUsed->format('U'));
        $replies = $pipe->execute();
        if (1 === ($replies[0] ?? 1)) {
            $this->client->del($this->prefix . $series);
            throw new TokenNotFoundException('No token found.');
        }
    }

    /**
     * @inheritDoc
     */
    public function createNewToken(PersistentTokenInterface $token)
    {
        /** @var \Redis $pipe */
        $pipe = $this->client->pipeline();
        $series = $token->getSeries();
        $pipe->hset($this->prefix . $series, 'class', $token->getClass());
        $pipe->hset($this->prefix . $series, 'username', $token->getUsername());
        $pipe->hset($this->prefix . $series, 'value', $token->getTokenValue());
        $pipe->hset($this->prefix . $series, 'lastUsed', $token->getLastUsed()->format('U'));
        $pipe->expire($this->prefix . $series, $this->ttl);
        $pipe->execute();
    }

}
