<?php

namespace BNS\App\AchievementBundle;

use BNS\App\AchievementBundle\DependencyInjection\Compiler\AchievementProviderCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppAchievementBundle extends Bundle
{

    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new AchievementProviderCompilerPass());
    }

}
