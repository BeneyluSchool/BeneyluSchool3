<?php

namespace BNS\App\MediaLibraryBundle\Tests\Transformer;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Form\Transformer\MediaToIdTransformer;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaToIdTransformerTest extends AppWebTestCase
{
    public function testTransformEmpty()
    {
        $mediaToIdTransformer = new MediaToIdTransformer();

        $this->assertEquals('', $mediaToIdTransformer->transform(null));
    }

    public function testTransformMedia()
    {
        $mediaToIdTransformer = new MediaToIdTransformer();

        $media = new Media();
        $media->setId(42);

        $this->assertEquals(42, $mediaToIdTransformer->transform($media));
    }

    public function testTransformInvalidObject()
    {
        $mediaToIdTransformer = new MediaToIdTransformer();

        $this->assertEquals('', $mediaToIdTransformer->transform('foo'));

        $this->assertEquals('', $mediaToIdTransformer->transform(new Blog()));
    }

    public function testReverseTransformEmpty()
    {
        $mediaToIdTransformer = new MediaToIdTransformer();

        $this->assertNull($mediaToIdTransformer->reverseTransform(null));
        $this->assertNull($mediaToIdTransformer->reverseTransform(false));
        $this->assertNull($mediaToIdTransformer->reverseTransform(''));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformInvalidId()
    {
        // init propel stuff
        $client = $this->getAppClient();

        $mediaToIdTransformer = new MediaToIdTransformer();

        $this->assertNull($mediaToIdTransformer->reverseTransform(9999));
    }

    public function testReverseTransformValidId()
    {
        // init propel stuff
        $client = $this->getAppClient();

        $mediaToIdTransformer = new MediaToIdTransformer();

        $media = MediaQuery::create()
            ->filterByUserId(1)
            ->filterByLabel('testReverseTransformValidId')
            ->findOneOrCreate()
        ;
        $media->save();
        $this->assertNotNull($media->getId());

        $reversedMedia = $mediaToIdTransformer->reverseTransform($media->getId());
        $this->assertNotNull($reversedMedia->getId());
        $this->assertEquals($media->getId(), $reversedMedia->getId());

        $reversedMedia->delete();
    }

}
