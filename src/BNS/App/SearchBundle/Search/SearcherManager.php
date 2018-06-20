<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 19/01/2018
 * Time: 13:09
 */

namespace BNS\App\SearchBundle\Search;


class SearcherManager
{
    /**
     * Array of registered providers
     *
     * @var array|AbstractSearchProvider[]
     */
    protected $providers = [];


    public function addProvider(AbstractSearchProvider $provider)
    {
        $this->providers[$provider->getName()] = $provider;
    }


    /**
     * Gets the list of registered search providers
     *
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param string $name
     * @return AbstractSearchProvider
     */
    public function getProvider($name)
    {
        $this->validateName($name);

        return $this->providers[$name];
    }

    /**
     * Checks if the given app name is valid, ie a provider with this name is registered.
     *
     * @param $name
     * @return bool
     */
    public function isValidName($name)
    {
        return isset($this->providers[$name]);
    }

    /**
     * Throws an exception if the given app name is not supported
     *
     * @param $name
     */
    private function validateName($name)
    {
        if (!$this->isValidName($name)) {
            throw new \InvalidArgumentException('Invalid search app name: '.$name);
        }
    }

    public function searcher($term = null, $providers = array())
    {
        if ($term == null || !count($providers)) {
            return null;
        }
        $results = array();
        foreach ($providers as $providerName) {
            $provider = $this->getProvider($providerName);
            $results = array_merge($results,$provider->search($term));
        }
        return $results;
    }
}
