<?php

namespace BNS\App\MediaLibraryBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class Attachments extends Constraint
{
    public $message = "INVALID_MEDIA";

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
