<?php

namespace BNS\App\CoreBundle\RichText;

use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Trait RichTextParser
 *
 * @package BNS\App\CoreBundle\RichText
 */
trait RichTextParser
{

    protected $container;
    protected $purifiers;

    public function getContainer()
    {
        if (!$this->container) {
            $this->container = BNSAccess::getContainer();
        }

        return $this->container;
    }

    /**
     * @param string $profile
     * @return \HTMLPurifier
     */
    public function getPurifier($profile = 'default')
    {
        if (!isset($this->purifiers[$profile])) {
            $purifier = $this->getContainer()->get('exercise_html_purifier.' . $profile);

            if (!$purifier instanceof \HTMLPurifier) {
                throw new \RuntimeException(sprintf('Service "exercise_html_purifier.%s" is not an HTMLPurifier instance.', $profile));
            }

            $this->purifiers[$profile] = $purifier;
        }

        return $this->purifiers[$profile];
    }

    /**
     * @param string $content
     * @return string
     */
    public function parse($content)
    {
        return $this->getContainer()->get('bns.media_library.public_media_parser')->parse(
            $this->getPurifier()->purify($content)
        );
    }

}
