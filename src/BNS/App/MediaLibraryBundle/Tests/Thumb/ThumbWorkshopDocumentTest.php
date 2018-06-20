<?php
namespace BNS\App\MediaLibraryBundle\Tests\Thumb;

use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaFolderUser;
use BNS\App\MediaLibraryBundle\Model\MediaPeer;
use BNS\App\MediaLibraryBundle\Thumb\ThumbWorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopContent;
use BNS\App\WorkshopBundle\Model\WorkshopContentPeer;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopPage;
use Imagine\Gd\Imagine;


class ThumbWorkshopDocumentTest extends AppWebTestCase
{
    public function testSupport()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);
        $this->assertTrue($thumbWorkshopDoc->supports($media));

        $externalMedia = new Media();
        $externalMedia->setExternalSource(MediaPeer::EXTERNAL_SOURCE_PAAS);
        $this->assertFalse($thumbWorkshopDoc->supports($externalMedia));
        $this->assertFalse($thumbWorkshopDoc->supports('foo'));
        $this->assertFalse($thumbWorkshopDoc->supports(null));
        $this->assertFalse($thumbWorkshopDoc->supports(false));

    }

    public function testGetThumbKey()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);

        $this->assertEquals(null, $thumbWorkshopDoc->getThumbKey('foo'));
        $this->assertEquals('wd:' . sha1(null), $thumbWorkshopDoc->getThumbKey($media));

        $media->setId(1234);
        $this->assertEquals('wd:' . sha1(1234), $thumbWorkshopDoc->getThumbKey($media));
    }

    public function testGetExtension()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);

        $this->assertEquals('jpg', $thumbWorkshopDoc->getExtension($media));
    }

    public function testSerialize()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $client = $this->getAppClient();
        $container = $client->getContainer();
        $contentManager = $container->get('bns.workshop.content.manager');

        $user = UserQuery::create()->filterByLogin('enseignant2')->findOne();

        $media = new Media();
        $media->setUserId($user->getId());
        $media->setCreatedAt('2016-12-16');
        $media->setLabel('Test');
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);
        $media->setMediaFolderType('USER');
        $media->setMediaFolderId($user->getMediaFolderRoot()->getId());
        $media->setFileMimeType('NONE');

        //setup
        $document = new WorkshopDocument();
        $document->setWorkshopContent(new WorkshopContent());
        $document->getWorkshopContent()->setType(WorkshopContentPeer::TYPE_DOCUMENT);
        $document->getWorkshopContent()->setMedia($media);
        $document->getWorkshopContent()->setAuthor($user);

        //create
        $document->setThemeCode('d');
        $document->addWorkshopPage(new WorkshopPage());
        $document->save();

        $contentManager->setContributorUserIds($document->getWorkshopContent(), array($user->getId()));
        $document->getWorkshopContent()->save();
        $serialized = $thumbWorkshopDoc->serialize($media);

        $serialized = json_decode($serialized);

        $this->assertEquals($document->getId(), $serialized->id);
    }

    public function testUnserialize()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $client = $this->getAppClient();
        $container = $client->getContainer();
        $contentManager = $container->get('bns.workshop.content.manager');

        $user = UserQuery::create()->filterByLogin('eleve')->findOne();

        $media = new Media();
        $media->setUserId($user->getId());
        $media->setCreatedAt('2016-12-16');
        $media->setLabel('Test');
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);
        $media->setMediaFolderType('USER');
        $media->setMediaFolderId($user->getMediaFolderRoot()->getId());
        $media->setFileMimeType('NONE');

        //setup
        $document = new WorkshopDocument();
        $document->setWorkshopContent(new WorkshopContent());
        $document->getWorkshopContent()->setType(WorkshopContentPeer::TYPE_DOCUMENT);
        $document->getWorkshopContent()->setMedia($media);
        $document->getWorkshopContent()->setAuthor($user);

        //create
        $document->setThemeCode('d');
        $document->addWorkshopPage(new WorkshopPage());
        $document->save();

        $contentManager->setContributorUserIds($document->getWorkshopContent(), array($user->getId()));
        $document->getWorkshopContent()->save();
        $serialized = $thumbWorkshopDoc->serialize($media);

        $this->assertEquals($document, $thumbWorkshopDoc->unserialize($serialized));
    }

    public function testOptions()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);

        $this->assertEquals(['vertical_align' => 'top'], $thumbWorkshopDoc->getOptions($media));
        $this->assertEquals(['vertical_align' => 'top'], $thumbWorkshopDoc->getOptions('foo'));
    }

    public function testGetImageEmptyMedia()
    {
        $thumbWorkshopDoc = $this->getThumbWorkshop();
        $media = new Media();
        $media->setTypeUniqueName(MediaPeer::TYPE_UNIQUE_NAME_ATELIER_DOCUMENT);

        $this->assertNull($thumbWorkshopDoc->getImage($this->getImagine(), $media));
    }

    protected function getImagine()
    {
        return new Imagine();
    }


    protected function getThumbWorkshop(callable $mockThumbUrl = null, callable  $mockSignUrl = null)
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $thumbUrlMockBuilder = $this->getMockBuilder('BNS\App\MediaLibraryBundle\Thumb\ThumbUrl');
        $thumbUrlMockBuilder->disableOriginalConstructor();
        $thumbUrlMock = $thumbUrlMockBuilder->getMock();

        if ($mockThumbUrl) {
            $thumbUrlMock = $mockThumbUrl($thumbUrlMock);
        }

        $thumbSignUrlMockBuilder = $this->getMockBuilder('BNS\App\CoreBundle\Routing\SignUrl');
        $thumbSignUrlMockBuilder->disableOriginalConstructor();
        $thumbSignUrlMock = $thumbSignUrlMockBuilder->getMock();

        if ($mockSignUrl) {
            $thumbSignUrlMock = $mockSignUrl($thumbSignUrlMock);
        }


        return new ThumbWorkshopDocument($thumbUrlMock, $container->get('router'), $thumbSignUrlMock);
    }
}
