<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use BNS\App\MediaLibraryBundle\FileSystem\BNSFileSystemManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocumentQuery;
use Gaufrette\Adapter;
use Gaufrette\Filesystem;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbMedia implements ThumbCreatorInterface
{
    /** @var  Adapter */
    protected $adapter;

    /** @var ThumbUrl */
    protected $thumbUrl;

    /**
     * ThumbMedia constructor.
     * @param Filesystem|BNSFileSystemManager $filesystemManager
     * @param ThumbUrl $thumbUrl
     */
    public function __construct($filesystemManager, ThumbUrl $thumbUrl)
    {
        $this->adapter = $filesystemManager->getAdapter();
        $this->thumbUrl = $thumbUrl;
    }

    /**
     * @inheritDoc
     */
    public function supports($object)
    {
        if (($object instanceof Media) &&
            (
                in_array($object->getTypeUniqueName(), [
                    MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT,
                    MediaPeer::TYPE_UNIQUE_NAME_ATELIER_QUESTIONNAIRE
                ])
            )
        ) {
            return false;
        }
        // Support thumb for media that or not from external source
        return ($object instanceof Media) && !$object->getExternalSource();
    }

    /**
     * @param ImagineInterface $imagine
     * @param Media $media
     * @return ImageInterface
     */
    public function getImage(ImagineInterface $imagine, $media)
    {
        if (!$this->supports($media)) {
            return null;
        }

        $media = $this->getOriginal($media);

        switch ($media->getTypeUniqueName()) {
            case MediaPeer::TYPE_UNIQUE_NAME_EMBEDDED_VIDEO:
                // TODO use metadata from api (dailymotion, youtube, vimeo to get better thumbnail)
            case MediaPeer::TYPE_UNIQUE_NAME_IMAGE:
                $key = $media->getFilePath();
                if (!$this->adapter->exists($key) || !($imageContent = $this->adapter->read($key))) {
                    return false;
                }

                return $imagine->load($imageContent);

            case MediaPeer::TYPE_UNIQUE_NAME_DOCUMENT:
                // TODO generate an image representing the document
                return null;

            case MediaPeer::TYPE_UNIQUE_NAME_LINK:
                $url = $media->getValue();
                if ($this->thumbUrl->supports($url)) {
                    return $this->thumbUrl->getImage($imagine, $url);
                }

                return false;

            case MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT:
                return null;

            case MediaPeer::TYPE_UNIQUE_NAME_ATELIER_AUDIO:
                return $imagine->open(__DIR__ . '/../../../../../web/angular/app/images/media-library/workshop-audio.png');

            default:
            case MediaPeer::TYPE_UNIQUE_NAME_VIDEO:
            case MediaPeer::TYPE_UNIQUE_NAME_FILE:
            case MediaPeer::TYPE_UNIQUE_NAME_PROVIDER_RESOURCE:
            case MediaPeer::TYPE_UNIQUE_NAME_HTML:
            case MediaPeer::TYPE_UNIQUE_NAME_HTML_BASE:
            case MediaPeer::TYPE_UNIQUE_NAME_AUDIO:

                // return a default image
        }

        return null;
    }

    /**
     * @param Media $object
     * @return string
     */
    public function getThumbKey($object)
    {
        if (!$this->supports($object)) {
            return null;
        }

        return 'm:' . sha1($this->getOriginalId($object));
    }

    /**
     * @param Media $object
     * @return string
     */
    public function getExtension($object)
    {
        if (MediaPeer::TYPE_UNIQUE_NAME_IMAGE === $object->getTypeUniqueName()) {
            if ('image/png' === $object->getFileMimeType()) {
                return ThumbCreatorManager::IMAGE_PNG;
            }
        }

        return ThumbCreatorManager::IMAGE_JPEG;
    }

    /**
     * @param Media $object
     * @return array
     */
    public function getOptions($object)
    {
        if (!$this->supports($object)) {
            return [];
        }

        switch ($object->getTypeUniqueName()) {
            case MediaPeer::TYPE_UNIQUE_NAME_LINK:
                $url = $object->getValue();
                if ($this->thumbUrl->supports($url)) {
                    return $this->thumbUrl->getOptions($object);
                }
                break;
        }

        return [];
    }

    /**
     * @param Media $object
     * @return string
     */
    public function serialize($object)
    {
        return 'media_' . $this->getOriginalId($object);
    }

    /**
     * @param string $serializedObject
     * @return Media|null
     */
    public function unserialize($serializedObject)
    {
        list($type, $id) = explode('_', $serializedObject . '_');

        if (('media' === $type) && $id) {
            return $this->getOriginal(MediaQuery::create()->findPk($id));
        }

        return null;
    }

    /**
     * Retrieve the original object if the given one is a copy.
     *
     * @param $object
     * @return Media
     */
    protected function getOriginal($object) {
        if ($object instanceof Media) {
            return $object->getOriginal() ?: $object;
        }

        return $object;
    }

    /**
     * Retrieve the original id of the given object, if it is a copy.
     *
     * @param $object
     * @return int
     */
    protected function getOriginalId($object) {
        if ($object instanceof Media) {
            return $object->getCopyFromId() ?: $object->getId();
        }

        return $object->getId();
    }
}
