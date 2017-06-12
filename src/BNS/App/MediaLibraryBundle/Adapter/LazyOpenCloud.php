<?php
namespace BNS\App\MediaLibraryBundle\Adapter;

use Gaufrette\Adapter\LazyOpenCloud as BaseLazyOpenCloud;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Log\PsrLogAdapter;
use Guzzle\Plugin\Log\LogPlugin;
use OpenCloud\Common\Exceptions\DeleteError;
use OpenCloud\ObjectStore\Exception\ObjectNotFoundException;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class LazyOpenCloud extends BaseLazyOpenCloud implements RemoteAdapter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Indicates whether the file exists
     *
     * @param string $key
     *
     * @return boolean
     */
    public function exists($key)
    {
        return $this->tryGetObjectMeta($key) !== false;
    }

    /**
     * Returns the last modified time
     *
     * @param string $key
     *
     * @return integer|boolean An UNIX like timestamp or false
     */
    public function mtime($key)
    {
        if ($object = $this->tryGetObjectMeta($key)) {
            return $object->getLastModified();
        }

        return false;
    }

    /**
     * Deletes the file
     *
     * @param string $key
     *
     * @return boolean
     */
    public function delete($key)
    {
        if (!$object = $this->tryGetObjectMeta($key)) {
            return false;
        }

        try {
            $object->delete();
        }
        catch (DeleteError $deleteError) {
            return false;
        }

        return true;
    }

    /**
     * Returns the checksum of the specified key
     *
     * @param string $key
     *
     * @return string
     */
    public function checksum($key)
    {
        if ($object = $this->tryGetObjectMeta($key)) {
            return $object->getETag();
        }

        return false;
    }

    /**
     * @param $key
     * @param int $expirationTime
     * @param string $httpMethod
     * @return null|string
     */
    public function getTemporaryUrl($key, $expirationTime = 3600, $httpMethod = 'GET')
    {
        if ($object = $this->tryGetObjectMeta($key)) {
            return $object->getTemporaryUrl($expirationTime, $httpMethod);
        }

        return false;
    }

    /**
     * Override to lazyload the container and add logger
     *
     * @return \OpenCloud\ObjectStore\Resource\Container
     */
    public function getContainer()
    {
        if (!$this->objectStore) {
            $this->objectStore = $this->objectStoreFactory->getObjectStore();
            if ($this->logger) {
                $this->objectStore->getClient()->addSubscriber(new LogPlugin(new PsrLogAdapter($this->logger)));
            }
        }

        return parent::getContainer();
    }

    /**
     * get the object store of the adapter
     * @return \OpenCloud\ObjectStore\Service
     */
    public function getObjectStore()
    {
        if (!$this->objectStore) {
            $this->objectStore = $this->objectStoreFactory->getObjectStore();
            if ($this->logger) {
                $this->objectStore->getClient()->addSubscriber(new LogPlugin(new PsrLogAdapter($this->logger)));
            }
        }

        return $this->objectStore;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $key
     *
     * @return \OpenCloud\ObjectStore\Resource\DataObject|false
     */
    protected function tryGetObjectMeta($key)
    {
        // fix key non alphanumeric
        $key = rawurlencode($key);
        try {
            return $this->getContainer()->getPartialObject($key);
        } catch (BadResponseException $e) {
            return false;
        } catch (ObjectNotFoundException $objFetchError) {
            return false;
        }
    }

    /**
     * @param string $key
     *
     * @return \OpenCloud\ObjectStore\Resource\DataObject|false
     */
    protected function tryGetObject($key)
    {
        // fix name non alphanumeric
        $key = rawurlencode($key);
        return parent::tryGetObject($key);
    }

}
