<?php

namespace BNS\App\CoreBundle\Twig\Extension;

/**
 * Class HTMLPurifierExtension
 *
 * @package BNS\App\CoreBundle\Twig\Extension
 */
class HTMLPurifierExtension extends \Exercise\HTMLPurifierBundle\Twig\HTMLPurifierExtension
{

    /**
     * @inheritDoc
     */
    public function purify($string, $profile = 'default')
    {
        $string = parent::purify($string, $profile);
        $string = str_replace(['{', '}'], ['&#123;', '&#125;'], $string);

        return '<div ng-non-bindable>' . $string . '</div>';
    }

}
