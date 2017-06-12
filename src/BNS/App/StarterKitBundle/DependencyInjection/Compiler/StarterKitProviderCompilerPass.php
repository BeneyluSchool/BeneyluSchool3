<?php

namespace BNS\App\StarterKitBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class StarterKitProviderCompilerPass
 *
 * @package BNS\App\StarterKitBundle\DependencyInjection\Compiler
 */
class StarterKitProviderCompilerPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('bns.starter_kit_manager')) {
            return;
        }

        // add all tagged providers to the starter kit manager
        $definition = $container->findDefinition('bns.starter_kit_manager');
        $taggedServices = $container->findTaggedServiceIds('bns.starter_kit_provider');
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addProvider', [new Reference($id)]);
        }
    }

}
