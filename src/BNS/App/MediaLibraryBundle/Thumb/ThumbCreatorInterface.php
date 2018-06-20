<?php

namespace BNS\App\MediaLibraryBundle\Thumb;

use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface ThumbCreatorInterface
{
    /**
     * return true if the ThumbCreator supports this $object
     * @param $object
     * @return boolean
     */
    public function supports($object);

    /**
     * Should return an ImageInterface that represent this object (with best quality)
     * @param ImagineInterface $imagine
     * @param $object
     * @return ImageInterface
     */
    public function getImage(ImagineInterface $imagine, $object);

    /**
     * return a hash (sha1) of the object prefixed with unique short ThumbCreator code ('m:', 'url:')
     * @param $object
     * @return string
     */
    public function getThumbKey($object);

    /**
     * @param $object
     * @return string (jpg, gif, png)
     */
    public function getExtension($object);

    /**
     * Should return an array of options used to create the thumbnail
     * @param $object
     * @return array
     */
    public function getOptions($object);

    /**
     * @param $object
     * @return string
     */
    public function serialize($object);

    /**
     * @param string $serializedObject
     * @return $object
     */
    public function unserialize($serializedObject);
}
