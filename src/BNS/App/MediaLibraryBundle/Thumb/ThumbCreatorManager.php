<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use BNS\App\CoreBundle\Events\ThumbnailRefreshEvent;
use Imagine\Filter\Transformation;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbCreatorManager
{
    const IMAGE_JPEG = 'jpg';
    const IMAGE_PNG = 'png';
    const IMAGE_GIF = 'gif';

    /** @var  ThumbCreatorInterface[] */
    protected $thumbCreators;

    /** @var  ThumbConfig[] */
    protected $thumbConfigs;

    /** @var Producer  */
    protected $producer;

    /** @var  \Redis|\Predis\Client */
    protected $redis;

    /** @var LoggerInterface  */
    protected $logger;

    /**
     * ThumbCreatorManager constructor.
     * @param array $thumbCreators
     * @param array $thumbConfigs
     * @param Producer $producer
     * @param $redis
     * @param LoggerInterface $logger
     */
    public function __construct(array $thumbCreators, array $thumbConfigs, Producer $producer, $redis, LoggerInterface $logger)
    {
        $this->thumbCreators = $thumbCreators;
        $this->thumbConfigs = [];
        foreach ($thumbConfigs as $thumbConfig) {
            $this->thumbConfigs[$thumbConfig->getName()] = $thumbConfig;
        }
        $this->producer = $producer;
        $this->redis = $redis;
        $this->logger = $logger;
    }

    /**
     * get a filesystem path for the thumbnail of the $object
     *
     * @param $object
     * @param string $configName
     *
     * @return boolean|string false if it doesn't exist or path to file (Gaufrette key)
     */
    public function getThumb($object, $configName)
    {
        if (!($config = $this->getConfig($configName))) {
            return false;
        }

        // Try all thumbCreator to find the right one
        $thumbCreator = null;
        $path = null;
        foreach ($this->thumbCreators as $creator) {
            if (!$creator->supports($object)) {
                continue;
            }
            if (!($path = $this->getPathFor($object, $configName, $creator))) {
                continue;
            }
            $thumbCreator = $creator;
            break;
        }

        if (!$thumbCreator || !$path) {
            $this->logger->error(sprintf('ThumbCreatorManager object unsupported to create thumb', [
                'object' => $object,
                'config' => $configName,
            ]));

            return false;
        }

        // try to find meta from cache
        $meta = $this->getMeta($path);
        if ($meta) {
            if ($meta['expires'] < time()) {
                // thumb expired we ask for an async rebuild
                $this->producer->publish($path);
            }

            return $path;
        }

        // normal process create a new thumb
        $image = $this->createThumb($object, $config, $thumbCreator);
        if ($image) {
            $this->saveImage($image, $path);

            $meta = $this->buildMeta($object, $configName, $thumbCreator, strtotime('+1 month'));
            $this->setMeta($meta, $path);

            return $path;
        }

        $this->logger->error(sprintf('ThumbCreatorManager cannot create thumb', [
            'object' => $object,
            'config' => $configName,
            'creator' => get_class($thumbCreator)
        ]));

        return false;
    }

    public function askRebuild($path)
    {
        if ($this->isPathValid($path)) {
            $this->producer->publish($path);
        }
    }

    public function hasThumb($path)
    {
        if (!($meta = $this->getMeta($path))) {
            return false;
        }

        return $meta['expires'] < time() ? ($meta['expires'] !== 0 ? 'expired' : false) : true;
    }

    /**
     * create a thumbnail from a $path
     * @param string $path
     * @return bool
     */
    public function createThumbFromPath($path)
    {
        if (!($meta = $this->getMeta($path))) {
            return false;
        }
        $creator = $meta['creator'];
        if (!$creator || !($config = $this->getConfig($meta['config']))) {
            return false;
        }

        foreach ($this->thumbCreators as $thumbCreator) {
            if ($thumbCreator instanceof $creator) {
                $object = $thumbCreator->unserialize($meta['object']);
                if ($object) {
                    $image = $this->createThumb($object, $config, $thumbCreator);
                    if ($image) {
                        $this->saveImage($image, $path);
                        $meta['expires'] = strtotime('+1 month');
                        $this->setMeta($meta, $path);

                        return true;
                    }
                    $this->logger->error('ThumbCreator: cannot create thumb image', [
                        'path' => $path,
                        'meta' => $meta
                    ]);

                    return false;
                }

                $this->logger->error('ThumbCreator: cannot create thumb, unable to unserialize object', [
                    'path' => $path,
                    'meta' => $meta
                ]);

                return false;
            }
        }

        $this->logger->error('ThumbCreator: cannot create thumb, no thumbCreator match message', [
            'path' => $path,
            'meta' => $meta
        ]);

        return false;
    }

    /**
     * This is the entry point to create a thumb from a variety of object ($url, Media, Minisite)
     *
     * @param $object
     * @param ThumbConfig $config
     * @return ImageInterface|false
     */
    public function createThumb($object, ThumbConfig $config, ThumbCreatorInterface $thumbCreator = null)
    {
        if (!$thumbCreator) {
            // try to find the right thumb creator
            foreach ($this->thumbCreators as $creator) {
                if ($creator->supports($object)) {
                    $thumbCreator = $creator;
                    break;
                }
            }
        } elseif (!$thumbCreator->supports($object)) {
            return false;
        }
        if (!$thumbCreator) {
            return false;
        }

        // try initiate Imagick driver
        $imagine = null;
        try {
            $imagine = new \Imagine\Imagick\Imagine();
        } catch (\RuntimeException $e) { }
        try {
            if (!$imagine) {
                $imagine = new \Imagine\Gd\Imagine();
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('ThumbCreatorManager Cannot instantiate Imagine driver (Gd or Imagick)', $e->getCode(), $e);
        }

        // the original image from the thumbCreator
        if ($image = $thumbCreator->getImage($imagine, $object)) {
            // create the thumbnail
            return $this->createThumbFromImage($imagine, $image, $config) ? : false;
        }

        return false;
    }

    /**
     *
     * @param $object
     * @param string $configName
     * @return bool|string
     */
    public function getPath($object, $configName)
    {
        foreach ($this->thumbCreators as $thumbCreator) {
            if ($thumbCreator->supports($object)) {
                if ($path = $this->getPathFor($object, $configName, $thumbCreator)) {
                    if ($this->getMeta($path)) {
                        return $path;
                    }
                    $meta = $this->buildMeta($object, $configName, $thumbCreator);
                    if ($this->setMeta($meta, $path)) {
                        return $path;
                    }
                }
                break;
            }
        }

        return false;
    }

    /**
     * set a thumbnail in an expired state, it will be regenerated on next access
     *
     * @param $object
     * @param $configName
     * @return bool
     */
    public function expire($object, $configName, $forced = false)
    {
        foreach ($this->thumbCreators as $thumbCreator) {
            if ($thumbCreator->supports($object)) {
                $path = $this->getPathFor($object, $configName, $thumbCreator);
                if (!$path) {
                    continue;
                }

                if (!$forced) {
                    $meta = $this->getMeta($path);
                    if ($meta) {
                        // expire the meta data by setting a past positive timestamp
                        $meta['expires'] = 10000;

                        return $this->setMeta($meta, $path);
                    }
                }

                // delete thumb key to force expiration and rebuild on next access
                $this->redis->del($path);

                return true;
            }
        }

        return false;
    }

    /**
     * check if the $path is a valid thumbnail path
     * @param $path
     * @return bool
     */
    public function isPathValid($path)
    {
        $matches = [];
        if (preg_match('#^tb/(?P<config>[0-9a-z_]+)/[0-9a-z]+/[0-9a-z]{2}/[0-9a-z]{2}/[0-9a-z]{40}\.(?P<extension>[a-z]+)$#', $path, $matches)) {
            // is config name valid
            if (!$this->isConfigNameValid($matches['config'])) {
                return false;
            }

            // is extension valid
            if (!isset($matches['extension']) || !in_array($matches['extension'], [self::IMAGE_GIF, self::IMAGE_JPEG, self::IMAGE_PNG], true)) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Create thumbnail meta that will be stored in redis
     * @param $object
     * @param string $configName
     * @param ThumbCreatorInterface $thumbCreator
     * @param int $expires
     * @return array
     */
    protected function buildMeta($object, $configName, ThumbCreatorInterface $thumbCreator, $expires = 0)
    {
        $meta = [
            'expires' => (int)$expires,
            'object' => $thumbCreator->serialize($object),
            'config' => $configName,
            'creator' => get_class($thumbCreator),
            'path' => $this->getPathFor($object, $configName, $thumbCreator),
        ];

        return $meta;
    }

    /**
     * build meta use to store cache info in redis
     * @param string $path
     * @return bool|array
     */
    protected function getMeta($path)
    {
        if ($this->isPathValid($path)) {
            $metaJson = $this->redis->get($path);
            if ($metaJson) {
                $meta = json_decode($metaJson, true);
                if ($this->isMetaValid($meta)) {
                    return $meta;
                }
            }
        }

        return false;
    }

    /**
     * Store meta data in redis
     * @param array $meta
     * @param string $path
     * @return bool
     */
    protected function setMeta(array $meta, $path)
    {
        if (!$this->isMetaValid($meta) || !$this->isPathValid($path)) {
            return false;
        }

        $this->redis->set($path, json_encode($meta));

        return true;
    }

    /**
     * validate the meta data
     * @param array $meta
     * @return bool
     */
    protected function isMetaValid($meta)
    {
        if (!$meta || !is_array($meta)) {
            return false;
        }

        $keys = [
            'expires',
            'object',
            'config',
            'creator',
            'path',
        ];

        if (count(array_diff(array_keys($meta), $keys)) > 0) {
            // missing some meta keys or extra keys
            return false;
        }

        if (!$this->isConfigNameValid($meta['config'])) {
            return false;
        }

        return true;
    }

    /**
     * check if config name isValid
     * @param $name
     * @return bool
     */
    protected function isConfigNameValid($name)
    {
        return $name && isset($this->thumbConfigs[$name]);
    }

    /**
     * @param string $name
     * @return ThumbConfig|bool|mixed
     */
    protected function getConfig($name)
    {
        if ($this->isConfigNameValid($name)) {
            return $this->thumbConfigs[$name];
        }

        return false;
    }

    /**
     * @param $object
     * @param string $configName
     * @param ThumbCreatorInterface $thumbCreator
     * @return bool|string
     */
    protected function getPathFor($object, $configName, ThumbCreatorInterface $thumbCreator)
    {
        $key = $thumbCreator->getThumbKey($object);
        if (!$key || !isset($this->thumbConfigs[$configName])) {
            return false;
        }

        $key = str_replace('//', '/', str_replace(':', '/', $key));
        $start = strripos($key, '/');

        return 'tb/' . $configName . '/' . substr($key, 0, $start) . substr($key, $start, 3) . '/' . substr($key, $start + 3, 2) . '/' . substr($key, $start + 1) . '.' . $thumbCreator->getExtension($object);
    }

    /**
     * this save the image to the file system and store data about it in redis
     *
     * @param ImageInterface $image
     * @param $path
     */
    protected function saveImage(ImageInterface $image, $path)
    {
        $image->save('gaufrette://bns_filesystem/' . $path);
    }


    /**
     * @param ImageInterface $image the image from witch we will create a thumb
     * @param ThumbConfig $config
     * @return ImageInterface|null|false the ImageInterface that represent the thumb or false when it's impossible to create thumb
     */
    protected function createThumbFromImage(ImagineInterface $imagine, ImageInterface $image, ThumbConfig $config)
    {
        try {
            $thumbMode = $config->getOption('thumb_mode');
            $transformation = new Transformation($imagine);

            $configSize = $config->getSize();
            $width = $configSize->getWidth() ? : null;
            $height = $configSize->getHeight() ? : null;

            $size = $image->getSize();
            $origWidth = $size->getWidth();
            $origHeight = $size->getHeight();
            if (null === $width || null === $height) {
                if (null === $height) {
                    $height = (int) (($width / $origWidth) * $origHeight);
                } elseif (null === $width) {
                    $width = (int) (($height / $origHeight) * $origWidth);
                }
            }

            // Too small => upscale
            if ($config->getOption('upscale') && ($origWidth < $width || $origHeight < $height)) {
                $widthRatio = $origWidth / $width;
                $heightRatio = $origHeight / $height;

                if ($widthRatio <= $heightRatio && $widthRatio < 1) {
                    $transformation->resize($size->widen($width));
                } elseif ($heightRatio < $widthRatio  && $heightRatio < 1) {
                    $transformation->resize($size->heighten($height));
                }
            }

            // TODO thumbnail position top center bottom
            $transformation->thumbnail(new Box($config->getWidth(), $config->getHeight()), $thumbMode);

            /** @var ImageInterface $image */
            $image = $transformation->apply($image);
            if ($config->getOption('fill')) {
                if ($image) {
                    $newSize = $image->getSize();

                    if ($newSize->getHeight() < $height || $newSize->getWidth() < $width) {
                        // fill the box if wrong ratio or smaller than required with transparent white background
                        $palette = new RGB();
                        $transparentImage = $imagine->create($config->getSize(), $palette->color('#FFFFFF', 0));
                        $x = round(($configSize->getWidth() - $newSize->getWidth()) / 2);
                        $y = round(($configSize->getHeight() - $newSize->getHeight()) / 2);

                        $transparentImage->paste($image, new Point($x, $y));

                        return $transparentImage;
                    }
                }
            }

            return $image;

        } catch (\Exception $e) {
            $this->logger->error(sprintf('MediaThumbCreator try to create thumb from content with config "%s" error "%s" : "%s"', $config->getName(), get_class($e), $e->getMessage()));

            return false;
        }
    }

    public function onThumbnailRefreshEvent(ThumbnailRefreshEvent $event)
    {
        $object = $event->getObject();
        $configName = $event->getConfigName();
        $syncRegen = $event->getSyncRegen();
        $this->expire($object, $configName, $syncRegen);
    }
}
