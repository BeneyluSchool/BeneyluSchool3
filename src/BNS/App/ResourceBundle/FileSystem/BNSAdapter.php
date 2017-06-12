<?php

namespace BNS\App\ResourceBundle\FileSystem;

use Gaufrette\Adapter\Cache;
use Gaufrette\File;
use Gaufrette\Adapter;
use Gaufrette\Adapter\InMemory as InMemoryAdapter;

class BNSAdapter extends Cache
{
	
	protected $source;

    /**
     * @var Adapter
     */
    protected $cache;

    /**
     * @var integer
     */
    protected $ttl;

    /**
     * @var Adapter
     */
    protected $serializeCache;

    /**
     * Constructor
     *
     * @param  Adapter $source  		The source adapter that must be cached
     * @param  Adapter $cache   		The adapter used to cache the source
     * @param  integer $ttl     		Time to live of a cached file
     * @param  Adapter $serializeCache  The adapter used to cache serializations
     */
    public function __construct(Adapter $source, Adapter $cache, $ttl = 0, Adapter $serializeCache = null)
    {
        $this->source = $source;
        $this->cache = $cache;
        $this->ttl = $ttl;

        if (!$serializeCache) {
            $serializeCache = new InMemoryAdapter();
        }
        $this->serializeCache = $serializeCache;
    }
    
    public function read($key)
    {

		if($this->cache->exists($key)){
			$contents = $this->cache->read($key);
		}else{
			if ($this->needsReload($key)) {
				$contents = $this->source->read($key);
				$this->cache->write($key, $contents);
			} else {
				$contents = false;
			}
		}
        return $contents;
    }
	
	public function exists($key)
    {
        if($this->cache->exists($key))
			return true;
		return $this->source->exists($key);
    }

}
