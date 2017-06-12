<?php

namespace BNS\App\CoreBundle\Routing;

use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * Class HashtagDecodedUrlGenerator
 *
 * @package BNS\App\CoreBundle\Routing
 */
class HashtagDecodedUrlGenerator extends UrlGenerator
{

    /**
     * {@inheritdoc}
     */
    protected $decodedChars = array(
        '%2F' => '/',
        '%40' => '@',
        '%3A' => ':',
        '%3B' => ';',
        '%2C' => ',',
        '%3D' => '=',
        '%2B' => '+',
        '%21' => '!',
        '%2A' => '*',
        '%7C' => '|',

        // BNS override: add the hash to unescaped characters, as the front Angular may need it
        '%23' => '#',
    );

}
