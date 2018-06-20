<?php
namespace BNS\App\MediaLibraryBundle\Tests\Thumb;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Thumb\ThumbMedia;
use BNS\App\MediaLibraryBundle\Thumb\ThumbUrl;
use Gaufrette\Adapter\InMemory;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Psr\Log\NullLogger;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbUrlTest extends AppWebTestCase
{
    public function testSupport()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertTrue($thumbUrl->supports('https://google.fr/'));
        $this->assertTrue($thumbUrl->supports('http://beneylu.com/'));

        $this->assertFalse($thumbUrl->supports(new Media()));
        $this->assertFalse($thumbUrl->supports('foo'));
        $this->assertFalse($thumbUrl->supports(null));
        $this->assertFalse($thumbUrl->supports(false));
    }

    public function testGetThumbKey()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertEquals(null, $thumbUrl->getThumbKey('foo'));
        $this->assertEquals('url:' . sha1('https://google.fr/'), $thumbUrl->getThumbKey('https://google.fr/'));
    }

    public function testGetExtension()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertEquals('jpg', $thumbUrl->getExtension('https://google.fr/'));

        $this->assertEquals('jpg', $thumbUrl->getExtension('foo'));
    }

    public function testSerialize()
    {
        $thumbUrl = $this->getThumbUrl();
        $url = 'http://beneylu.com';

        $this->assertEquals($url, $thumbUrl->serialize($url));
    }

    public function testUnserialize()
    {
        $thumbUrl = $this->getThumbUrl();
        $url = 'http://beneylu.com';

        $this->assertEquals($url, $thumbUrl->unserialize($url));
        $this->assertEquals($url, $thumbUrl->unserialize($thumbUrl->serialize($url)));
    }

    public function testOptions()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertEquals(['vertical_align' => 'top'], $thumbUrl->getOptions('https://beneylu.com/'));

        $this->assertEquals(['vertical_align' => 'top'], $thumbUrl->getOptions(new Media()));
        $this->assertEquals(['vertical_align' => 'top'], $thumbUrl->getOptions('foo'));
    }

    public function testGetImageEmptyUrl()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertNull($thumbUrl->getImage($this->getImagine(), 'foo'));
    }

    public function testGetImageBeneyluUrl()
    {
        $thumbUrl = $this->getThumbUrl();

        $this->assertInstanceOf('Imagine\Image\ImageInterface', $thumbUrl->getImage($this->getImagine(), 'http://beneylu.com'));
    }

    protected function getImagine()
    {
        return new Imagine();
    }

    protected function getThumbUrl(callable $mockThumbUrl = null)
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        return new ThumbUrl($container->get('validator'), new NullLogger(), $container->get('knp_snappy.image'));
    }
}
