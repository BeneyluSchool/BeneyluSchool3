<?php

namespace BNS\App\WorkshopBundle\Manager;

use Symfony\Component\Yaml\Parser;

/**
 * Class ConfigurationManager
 *
 * @package BNS\App\WorkshopBundle\Manager
 */
class ConfigurationManager
{

    /**
     * Path of the yaml file to load, relative to the bundle
     *
     * @var string
     */
    protected $path = '';

    /**
     * The parsed data
     *
     * @var array
     */
    protected $data = null;

    public function __construct($path)
    {
        $path = __DIR__ . '/../' . $path;
        if (!file_exists($path)) {
            throw new \InvalidArgumentException('Invalid file path: ' . $path);
        }

        $this->path = $path;
    }

    /**
     * Gets the collection of configurations
     *
     * @return array
     */
    public function getList()
    {
        if (!$this->data) {
            $this->load();
        }

        // assume that all collection items are grouped under a single root key,
        // so simply return its value
        return reset($this->data);
    }

    /**
     * Gets a list of configuration objects filtered on the given property by
     * the given value
     *
     * @param string $propery
     * @param $value
     * @return array
     */
    public function findBy($propery, $value)
    {
        $results = array();

        foreach ($this->getList() as $object) {
            if (isset($object[$propery]) && $object[$propery] == $value) {
                $results[] = $object;
            }
        }

        return $results;
    }

    /**
     * Returns the first configuration object whose given property matches the
     * given value
     *
     * @param $propery
     * @param $value
     * @return array
     */
    public function findOneBy($propery, $value)
    {
        foreach ($this->findBy($propery, $value) as $object) {
            return $object;
        }

        return null;
    }

    protected function load()
    {
        $parser = new Parser();
        $this->data = $parser->parse(file_get_contents($this->path));
    }

}
