<?php

namespace BNS\App\StatisticsBundle;

use BNS\App\StatisticsBundle\DependencyInjection\Compiler\StatisticConfigProviderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppStatisticsBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new StatisticConfigProviderCompilerPass());
    }
}
