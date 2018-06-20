<?php
namespace BNS\App\MediaLibraryBundle\Tests\Thumb;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Thumb\ThumbMedia;
use Gaufrette\Adapter\InMemory;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbMediaTest extends AppWebTestCase
{
    public function testSupport()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertTrue($thumbMedia->supports(new Media()));

        $externalMedia = new Media();
        $externalMedia->setExternalSource(MediaPeer::EXTERNAL_SOURCE_PAAS);
        $this->assertFalse($thumbMedia->supports($externalMedia));
        $this->assertFalse($thumbMedia->supports('foo'));
        $this->assertFalse($thumbMedia->supports(null));
        $this->assertFalse($thumbMedia->supports(false));
    }

    public function testGetThumbKey()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertEquals(null, $thumbMedia->getThumbKey('foo'));
        $this->assertEquals('m:' . sha1(null), $thumbMedia->getThumbKey(new Media()));

        $media = new Media();
        $media->setId(1234);
        $this->assertEquals('m:' . sha1(1234), $thumbMedia->getThumbKey($media));
    }

    public function testGetExtension()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertEquals('jpg', $thumbMedia->getExtension(new Media()));

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_IMAGE);
        $media->setFileMimeType('image/png');
        $this->assertEquals('png', $thumbMedia->getExtension($media));
    }

    public function testSerialize()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertEquals('media_', $thumbMedia->serialize(new Media()));

        $media = new Media();
        $media->setId(123);
        $this->assertEquals('media_123', $thumbMedia->serialize($media));
    }

    public function testUnserialize()
    {
        // init propel connection
        $this->getAppClient();

        $thumbMedia = $this->getThumbMedia();

        $this->assertNull($thumbMedia->unserialize('foo'));
        $this->assertNull($thumbMedia->unserialize('media_999999'));
        $this->assertNull($thumbMedia->unserialize('media_aze'));

        $user = UserQuery::create()->filterByLogin('enseignant')->findOne();
        $media = new Media();
        $media->setUserId($user->getId());
        $media->save();

        $object = $thumbMedia->unserialize($thumbMedia->serialize($media));
        $this->assertInstanceOf('BNS\App\MediaLibraryBundle\Model\Media', $object);
        $this->assertEquals($object->getId(), $media->getId());
    }

    public function testOptions()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertEquals([], $thumbMedia->getOptions(new Media()));
        $this->assertEquals([], $thumbMedia->getOptions('foo'));

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_LINK);
        $this->assertEquals([], $thumbMedia->getOptions($media));

        $that = $this;
        $thumbMedia = $this->getThumbMedia(function($thumbUrlMock) use ($that) {
            /** @var $thumbUrlMock \PHPUnit_Framework_MockObject_MockObject */
            $thumbUrlMock->expects($that->once())
                ->method('supports')
                ->will($that->returnValue(true))
            ;
            $thumbUrlMock->expects($that->once())
                ->method('getOptions')
                ->will($that->returnValue(['foo' => 'bar']))
            ;
            return $thumbUrlMock;
        });

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_LINK);
        $media->setValue('https://google.fr/');
        $this->assertEquals(['foo' => 'bar'], $thumbMedia->getOptions($media));
    }

    public function testGetImageEmptyMedia()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertNull($thumbMedia->getImage($this->getImagine(), new Media()));

        $this->assertNull($thumbMedia->getImage($this->getImagine(), 'foo'));
    }

    public function testGetImageEmptyMediaImage()
    {
        $thumbMedia = $this->getThumbMedia();

        $this->assertNull($thumbMedia->getImage($this->getImagine(), 'foo'));

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_IMAGE);
        $media->setCreatedAt('2016-10-10');
        $media->setFilename('foo.jpg');
        $this->assertFalse($thumbMedia->getImage($this->getImagine(), $media));
    }

    public function testGetImageMediaImageGif()
    {
        $thumbMedia = $this->getThumbMedia();

        $media = new Media();
        $media->setUserId(1);
        $media->setId(1);
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_IMAGE);
        $media->setCreatedAt('2016-10-10');
        $media->setFilename('small.gif');
        $this->assertInstanceOf('Imagine\Image\ImageInterface', $thumbMedia->getImage($this->getImagine(), $media));
    }

    public function testGetImageEmptyMediaDocumentAudio()
    {
        $thumbMedia = $this->getThumbMedia();

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_AUDIO);
        $this->assertInstanceOf('Imagine\Image\ImageInterface', $thumbMedia->getImage($this->getImagine(), $media));
    }

    public function testGetImageEmptyMediaDocument()
    {
        $thumbMedia = $this->getThumbMedia();

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_DOCUMENT);
        $this->assertNull($thumbMedia->getImage($this->getImagine(), $media));
    }

    public function testGetImageEmptyMediaWorkshopDocument()
    {
        $thumbMedia = $this->getThumbMedia();

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);
        $this->assertNull($thumbMedia->getImage($this->getImagine(), $media));
    }

    public function testGetImageEmptyMediaLink()
    {
        $that = $this;
        $thumbMedia = $this->getThumbMedia(function($thumbUrlMock) use ($that){
            /** @var $thumbUrlMock \PHPUnit_Framework_MockObject_MockObject */
            $thumbUrlMock->expects($that->once())->method('supports')->will($that->returnValue(false));

            return $thumbUrlMock;
        });

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_LINK);
        $media->setValue('foo');
        $this->assertFalse($thumbMedia->getImage($this->getImagine(), $media));


        $thumbMedia = $this->getThumbMedia(function($thumbUrlMock) use ($that) {
            /** @var $thumbUrlMock \PHPUnit_Framework_MockObject_MockObject */
            $thumbUrlMock->expects($that->once())
                ->method('supports')
                ->will($that->returnValue(true))
            ;
            $thumbUrlMock->expects($that->once())
                ->method('getImage')
                ->will($that->returnValue((new Imagine())->create(new Box(150, 150))))
            ;
            return $thumbUrlMock;
        });

        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_LINK);
        $media->setValue('http://google.fr/');
        $this->assertInstanceOf('Imagine\Image\ImageInterface', $thumbMedia->getImage($this->getImagine(), $media));
    }

    protected function getImagine()
    {
        return new Imagine();
    }

    protected function getThumbMedia(callable $mockThumbUrl = null)
    {
        $thumbUrlMockBuilder = $this->getMockBuilder('BNS\App\MediaLibraryBundle\Thumb\ThumbUrl');
        $thumbUrlMockBuilder->disableOriginalConstructor();
        $thumbUrlMock = $thumbUrlMockBuilder->getMock();

        if ($mockThumbUrl) {
            $thumbUrlMock = $mockThumbUrl($thumbUrlMock);
        }

        $fileSystem = new \Gaufrette\Filesystem(new InMemory([
            '2016_10_10/foo.jpg' => 'fake image',
            '2016_10_10/1/1/small.gif' => base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==')
        ]));

        return new ThumbMedia($fileSystem, $thumbUrlMock);
    }
}
