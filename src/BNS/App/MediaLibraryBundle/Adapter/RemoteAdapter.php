<?php

namespace BNS\App\MediaLibraryBundle\Adapter;

use OpenCloud\ObjectStore\Resource\Container;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
interface RemoteAdapter
{
    /**
     * @return Container
     */
    public function getContainer();
}
