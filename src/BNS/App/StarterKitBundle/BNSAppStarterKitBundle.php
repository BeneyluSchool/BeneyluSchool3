<?php

namespace BNS\App\StarterKitBundle;

use BNS\App\StarterKitBundle\DependencyInjection\Compiler\StarterKitProviderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppStarterKitBundle extends Bundle
{
    
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        
        $container->addCompilerPass(new StarterKitProviderCompilerPass());
    }

}
