<?php

namespace BNS\App\StoreBundle\Client\Message;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\StoreBundle\Client\StoreClient;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class Request
{
    /**
     * @var StoreClient
     */
    private $client;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $queries;

    /**
     * @var array
     */
    private $parameters;

    /**
     * @var boolean
     */
    private $useCache = false;
    
    /**
     * @var ExtendedDateTime 
     */
    private $timeToLive = null;

    
    /**
     * @param StoreClient $storeClient
     * @param string      $uri
     * @param string      $method
     * @param array       $queries
     * @param array       $parameters
     */
    public function __construct(StoreClient $storeClient, $uri, $method, array $queries = array(), array $parameters = array())
    {
        $this->client     = $storeClient;
        $this->uri        = $uri;
        $this->method     = $method;
        $this->queries    = $queries;
        $this->parameters = $parameters;
    }

    /**
     * Send the request
     */
    public function send()
    {
        return $this->client->send($this);
    }

    /**
     * Use the cache before sending the request
     *
     * @return Request
     */
    public function useCache()
    {
        $this->useCache = true;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isUseCache()
    {
        return $this->useCache;
    }

    /**
     * @param int $timeToLive Time to live before the cache will be deleted
     *
     * @return Request
     *
     * @throws \InvalidArgumentException
     */
    public function expires($timeToLive)
    {
        try {
            $date = new ExtendedDateTime($timeToLive);
        }
        catch (\Exception $e) {
            $date = new ExtendedDateTime();
            $date->modify($timeToLive);
        }

        if ($date->getTimestamp() < time()) {
            throw new \InvalidArgumentException('The "time to live" parameter must be in the future !');
        }

        $this->timeToLive = $date;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeToLive()
    {
        return $this->timeToLive->getTimestamp() - time();
    }

    /**
     * @return boolean
     */
    public function hasTimeToLive()
    {
        return null != $this->timeToLive;
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }
}