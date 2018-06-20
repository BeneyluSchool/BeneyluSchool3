<?php

namespace BNS\App\CorrectionBundle\Model;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface CorrectionInterface
{
    /**
     * Should be implemented on any corrected Object
     * @return string (e.g. BLOG_CORRECTION)
     */
    public static function getCorrectionRightName();
}
