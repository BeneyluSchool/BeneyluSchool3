<?php

namespace BNS\App\SearchBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class StarterKitProviderCompilerPass
 *
 * @package BNS\App\SearchBundle\DependencyInjection\Compiler
 */
class SearchProviderCompilerPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('search.searcher')) {
            return;
        }

        // add all tagged providers to the searcher manager
        $definition = $container->findDefinition('search.searcher');
        $taggedServices = $container->findTaggedServiceIds('bns.search_provide');
        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addProvider', [new Reference($id)]);
        }
    }

}
