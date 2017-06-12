<?php

namespace BNS\CommonBundle;

use BNS\CommonBundle\DependencyInjection\Compiler\AssetVersionStrategyCompilerPass;
use BNS\CommonBundle\DependencyInjection\Compiler\PropelParamConverterCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSCommonBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new AssetVersionStrategyCompilerPass());
        $container->addCompilerPass(new PropelParamConverterCompilerPass());
    }
}
