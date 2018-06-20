<?php

namespace BNS\App\MediaLibraryBundle\Validator\Constraints;

use BNS\App\MediaLibraryBundle\Manager\MediaLibraryManager;
use BNS\App\MediaLibraryBundle\Manager\MediaLibraryRightManager;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AttachmentsValidator extends ConstraintValidator
{
    /**
     * @var MediaLibraryManager
     */
    protected $mediaLibraryRightManager;

    public function __construct(MediaLibraryRightManager $mediaLibraryRightManager)
    {
        $this->mediaLibraryRightManager = $mediaLibraryRightManager;
    }

    /**
     * @inheritDoc
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$object || !method_exists($object,'getAttachments')) {
            return;
        }
        $oldAttachments = $object->getAttachments(new \Criteria());
        foreach ($object->getAttachments() as $key => $media) {
            if (!$media) {
                continue;
            }
            if (!$this->mediaLibraryRightManager->canReadMedia($media)) {
                foreach ($oldAttachments as $oldMedia) {
                    // keep media already in the collection
                    if ($media->getId() === $oldMedia->getId()) {
                        continue 2;
                    }
                }
                $this->context->buildViolation($constraint->message)
                    ->setInvalidValue($media->getLabel())
                    ->addViolation()
                ;
            }
        }
    }

}
