<?php

namespace BNS\App\MediaLibraryBundle\Tests\Validator;

use BNS\App\CoreBundle\Model\Blog;
use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\CorrectionBundle\Model\Correction;
use BNS\App\CorrectionBundle\Model\CorrectionAnnotation;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\MediaLibraryBundle\Validator\Constraints\Attachments;
use Symfony\Component\Validator\Constraint;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AttachmentsValidatorTest extends AppWebTestCase
{
    public function testEmptyObject()
    {
        $constraint = new Attachments();

        $errors = $this->getValidator()->validate(null, [
            $constraint
        ]);

        $this->assertCount(0, $errors);
    }

    public function testInvalidObject()
    {
        $constraint = new Attachments();

        $errors = $this->getValidator()->validate(new Blog(), [
            $constraint
        ]);

        $this->assertCount(0, $errors);
    }

    public function testInvalidMedia()
    {
        $constraint = new Attachments();

        $media = new Media();
        $media->setId(9999);

        $attachments = new \PropelObjectCollection();
        $attachments->append($media);

        $correctionAnnotation = new CorrectionAnnotation();
        $correctionAnnotation->setAttachments($attachments);

        $errors = $this->getValidator()->validate($correctionAnnotation, [
            $constraint
        ]);

        $this->assertCount(1, $errors);
    }

    public function testValidMedia()
    {
        $user = $this->logIn('enseignant');
        $constraint = new Attachments();

        $media = MediaQuery::create()
            ->filterByUserId($user->getId())
            ->filterByLabel('testValidMedia')
            ->findOneOrCreate()
        ;
        $media->save();

        $attachments = new \PropelObjectCollection();
        $attachments->append($media);

        $correctionAnnotation = new CorrectionAnnotation();
        $correctionAnnotation->setAttachments($attachments);

        $errors = $this->getValidator()->validate($correctionAnnotation, [
            $constraint
        ]);

        $this->assertCount(0, $errors);

        $media->delete();
    }

    public function testAlreadyOnObjectMedia()
    {
        $user = $this->logIn('enseignant');
        $constraint = new Attachments();

        $media = MediaQuery::create()
            ->filterByUserId($user->getId())
            ->filterByLabel('testValidMedia')
            ->findOneOrCreate()
        ;
        $media->save();

        $attachments = new \PropelObjectCollection();
        $attachments->append($media);

        $correction = new Correction();
        $correctionAnnotation = new CorrectionAnnotation();
        $correctionAnnotation->setCorrection($correction);
        $correctionAnnotation->setAttachments($attachments);
        $correctionAnnotation->save();

        $eleve = $this->login('eleve');

        $mediaEleve = MediaQuery::create()
            ->filterByUserId($eleve->getId())
            ->filterByLabel('testAlreadyOnObjectMedia')
            ->findOneOrCreate()
        ;
        $mediaEleve->save();

        $attachments = $correctionAnnotation->getAttachments();
        $attachments->append($mediaEleve);
        $correctionAnnotation->setAttachments($attachments);


        $errors = $this->getValidator()->validate($correctionAnnotation, [
            $constraint
        ]);

        $this->assertCount(0, $errors);

        $mediaEleve->delete();
        $media->delete();
        $correctionAnnotation->delete();
        $correction->delete();
    }

    public function testNullMedia()
    {
        $user = $this->logIn('enseignant');
        $constraint = new Attachments();

        $media = MediaQuery::create()
            ->filterByUserId($user->getId())
            ->filterByLabel('testValidMedia')
            ->findOneOrCreate()
        ;
        $media->save();

        $attachments = new \PropelObjectCollection();
        $attachments->append($media);
        $attachments->append(null);

        $correctionAnnotation = new CorrectionAnnotation();
        $correctionAnnotation->setAttachments($attachments);

        $errors = $this->getValidator()->validate($correctionAnnotation, [
            $constraint
        ]);

        $this->assertCount(0, $errors);
        $this->assertCount(2, $attachments);

        $media->delete();
    }

    protected function getValidator()
    {
        $client = $this->getAppClient();
        $container = $client->getContainer();

        return $container->get('validator');
    }
}
