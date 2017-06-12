<?php

namespace BNS\App\MainBundle\StarterKit;

use BNS\App\StarterKitBundle\StarterKit\AbstractStarterKitProvider;

/**
 * Class MainStarterKitProvider
 *
 * @package BNS\App\MainBundle\StarterKit
 */
class MainStarterKitProvider extends AbstractStarterKitProvider
{

    /**
     * Module unique name concerned by this starter kit
     *
     * @return string
     */
    public function getName()
    {
        return 'MAIN';
    }

}
