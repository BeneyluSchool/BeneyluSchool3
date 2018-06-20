<?php
namespace BNS\App\MediaLibraryBundle\Tests\Thumb;

use BNS\App\MediaLibraryBundle\Thumb\ThumbConfig;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testThumbConfigDefaultOptions()
    {
        $name = 'small';
        $width = 180;
        $height = 180;

        $thumbConfig = new ThumbConfig($name, $width, $height);

        $this->assertEquals($name, $thumbConfig->getName());
        $this->assertEquals($name, (string)$thumbConfig);
        $this->assertEquals($width, $thumbConfig->getWidth());
        $this->assertEquals($height, $thumbConfig->getHeight());
        $this->assertInstanceOf('Imagine\Image\BoxInterface', $thumbConfig->getSize());
        $this->assertInstanceOf('Imagine\Image\BoxInterface', $thumbConfig->getBox());

        $this->assertNull($thumbConfig->getOption('foo'));

        $this->assertFalse($thumbConfig->getOption('fill'));
        $this->assertTrue($thumbConfig->getOption('upscale'));
        $this->assertEquals(ImageInterface::THUMBNAIL_OUTBOUND, $thumbConfig->getOption('thumb_mode'));
    }

    /**
     * @dataProvider thumbConfigData
     */
    public function testThumbConfigCustomOptions($name, $width, $height, array $options)
    {
        $thumbConfig = new ThumbConfig($name, $width, $height, $options);

        $this->assertEquals($name, $thumbConfig->getName());
        $this->assertInternalType('int', $width, $thumbConfig->getWidth());
        $this->assertInternalType('int', $height, $thumbConfig->getHeight());
        $this->assertInstanceOf('Imagine\Image\BoxInterface', $thumbConfig->getSize());
        $this->assertInstanceOf('Imagine\Image\BoxInterface', $thumbConfig->getBox());

        foreach ($options as $option => $value) {
            $this->assertEquals($value, $thumbConfig->getOption($option));
        }

    }

    public function thumbConfigData()
    {
        return [
            ['test1', 250, 250, ['fill' => false, 'foo' => 'bar']],
            ['test2', 1250, 250, ['fill' => false, 'upscale' => true, 'foo' => 'bar']],
            ['test3', 1250, 250, ['fill' => false, 'upscale' => true, 'foo' => 'bar']],
            ['test4', 1, 2, []]
        ];
    }
}
