<?php

namespace BNS\App\MediaLibraryBundle\StarterKit;

use BNS\App\StarterKitBundle\StarterKit\AbstractStarterKitProvider;

/**
 * Class MediaLibraryStarterKitProvider
 *
 * @package BNS\App\MediaLibraryBundle\StarterKit
 */
class MediaLibraryStarterKitProvider extends AbstractStarterKitProvider
{

    /**
     * Module unique name concerned by this starter kit
     *
     * @return string
     */
    public function getName()
    {
        return 'MEDIA_LIBRARY';
    }

}
