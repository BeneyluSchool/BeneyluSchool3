<?php

namespace BNS\App\InstallBundle;

use BNS\App\InstallBundle\DependencyInjection\Compiler\InstallProcessesCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppInstallBundle extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new InstallProcessesCompilerPass());
    }

}
