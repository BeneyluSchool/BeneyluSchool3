<?php

namespace BNS\App\AchievementBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class AchievementProviderCompilerPass
 *
 * @package BNS\App\StarterKitBundle\DependencyInjection\Compiler
 */
class AchievementProviderCompilerPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('bns.achievement_manager')) {
            return;
        }

        // add all tagged providers to the achievement manager
        $definition = $container->findDefinition('bns.achievement_manager');
        $taggedServices = $container->findTaggedServiceIds('bns.achievement_provider');
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addProvider', [new Reference($id)]);
        }
    }

}
