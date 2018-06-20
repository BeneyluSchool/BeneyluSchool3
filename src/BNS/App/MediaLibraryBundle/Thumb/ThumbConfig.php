<?php
namespace BNS\App\MediaLibraryBundle\Thumb;

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbConfig
{
    /** @var Box  */
    protected $box;

    /** @var string the config name */
    protected $name;

    /** @var array  */
    protected $options;

    /**
     * ThumbConfig constructor.
     * @param string $name
     * @param int $width
     * @param int $height
     * @param array|null $options
     */
    public function __construct($name, $width, $height, array $options = null)
    {
        $this->name = $name;
        $this->box = new Box($width, $height);
        $options = $options ?: array();

        $this->options = array_merge([
            'fill' => false,
            'thumb_mode' => ImageInterface::THUMBNAIL_OUTBOUND,
            'upscale' => true,
        ], $options);
    }

    public function __toString()
    {
        return $this->getName();
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->box->getHeight();
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->box->getWidth();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Box
     */
    public function getBox()
    {
        return $this->box;
    }

    /**
     * @return Box
     */
    public function getSize()
    {
        return $this->getBox();
    }

    public function getOption($name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        return null;
    }
}
