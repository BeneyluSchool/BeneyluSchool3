<?php

namespace BNS\App\MediaLibraryBundle;

use BNS\App\MediaLibraryBundle\DependencyInjection\Compiler\ThumbCreatorCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppMediaLibraryBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ThumbCreatorCompilerPass());
    }

}
