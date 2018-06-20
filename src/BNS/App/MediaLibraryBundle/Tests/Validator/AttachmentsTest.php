<?php

namespace BNS\App\MediaLibraryBundle\Tests\Validator;

use BNS\App\MediaLibraryBundle\Validator\Constraints\Attachments;
use Symfony\Component\Validator\Constraint;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AttachmentsTest extends \PHPUnit_Framework_TestCase
{
    public function testAttachments()
    {
        $constraint = new Attachments();

        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $constraint->getTargets());
        $this->assertEquals('INVALID_MEDIA', $constraint->message);
    }
}
