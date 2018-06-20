<?php

namespace BNS\App\CorrectionBundle\Tests\Model;

use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CorrectionBundle\Model\Correction;
use BNS\App\CorrectionBundle\Model\CorrectionAnnotation;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class CorrectionTest extends AppWebTestCase
{
    public function testSetObjectWithoutId()
    {
        $correction = new Correction();

        $this->assertNull($correction->getObjectId());
        $this->assertNull($correction->getObjectClass());

        $correction->setObject(new BlogArticle());

        $this->assertNull($correction->getObjectId());
        $this->assertEquals('BNS\App\CoreBundle\Model\BlogArticle', $correction->getObjectClass());
    }

    public function testSetObjectWithId()
    {
        $correction = new Correction();

        $this->assertNull($correction->getObjectId());
        $this->assertNull($correction->getObjectClass());

        $article = new BlogArticle();
        $article->setId(9999);

        $correction->setObject($article);

        $this->assertEquals(9999, $correction->getObjectId());
        $this->assertEquals('BNS\App\CoreBundle\Model\BlogArticle', $correction->getObjectClass());
    }

    public function testPreDelete()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        $correction = new Correction();
        $correctionAnnotation = new CorrectionAnnotation();
        $correction->addCorrectionAnnotation($correctionAnnotation);
        $correction->save();


        $this->assertCount(1, $correction->getCorrectionAnnotations());
        $this->assertEquals(1, $correction->countCorrectionAnnotations(new \Criteria()));
        $correction->delete();
        $this->assertEquals(0, $correction->countCorrectionAnnotations(new \Criteria()));
    }

}
