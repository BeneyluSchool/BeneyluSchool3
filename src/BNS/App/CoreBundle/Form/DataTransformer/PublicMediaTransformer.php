<?php

namespace BNS\App\CoreBundle\Form\DataTransformer;

use BNS\App\MediaLibraryBundle\Parser\PublicMediaParser;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Class PublicMediaTransformer
 *
 * @package BNS\App\CoreBundle\Form\DataTransformer
 */
class PublicMediaTransformer implements DataTransformerInterface
{

    private $parser;

    public function __construct(PublicMediaParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @see Symfony\Component\Form\DataTransformerInterface::transform()
     */
    public function transform($value)
    {
        return $this->parser->parse($value);
    }

    /**
     * @see Symfony\Component\Form\DataTransformerInterface::reverseTransform()
     */
    public function reverseTransform($value)
    {
        return $value;
    }

}
