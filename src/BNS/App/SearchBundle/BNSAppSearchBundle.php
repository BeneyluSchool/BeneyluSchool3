<?php

namespace BNS\App\SearchBundle;

use BNS\App\SearchBundle\DependencyInjection\Compiler\SearchProviderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppSearchBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new SearchProviderCompilerPass());
    }
}
