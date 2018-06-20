<?php

namespace BNS\App\MediaLibraryBundle\Form\Transformer;

use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaToIdTransformer implements DataTransformerInterface
{
    /**
     * transform a Media to a string (id)
     *
     * @param Media $media
     *
     * @return null|integer
     */
    public function transform($media)
    {
        if (null === $media) {
            return '';
        }

        if ($media instanceof Media) {
            return $media->getId();
        }

        return '';
    }

    /**
     * transforms a string to a Media
     *
     * @param int $mediaId
     *
     * @return Media|null
     */
    public function reverseTransform($mediaId)
    {
        if (!$mediaId) {
            return null;
        }

        $media = MediaQuery::create()->findPk($mediaId);

        if (null === $media) {
            // causes a validation error
            // this message is not shown to the user
            // see the invalid_message option
            throw new TransformationFailedException(sprintf(
                'A media with id "%s" does not exist!',
                $mediaId
            ));
        }

        return $media;
    }
}
