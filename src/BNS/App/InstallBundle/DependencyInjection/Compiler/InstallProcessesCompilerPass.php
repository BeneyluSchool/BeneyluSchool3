<?php

namespace BNS\App\InstallBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class InstallProcessesCompilerPass
 *
 * @package BNS\App\InstallBundle\DependencyInjection\Compiler
 */
class InstallProcessesCompilerPass implements CompilerPassInterface
{

    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     *
     * @api
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('install_manager')) {
            return;
        }

        $definition = $container->findDefinition('install_manager');

        // find all tagged services and add them to the install manager
        $taggedServices = $container->findTaggedServiceIds('bns.install_process');
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition->addMethodCall('addProcessService', array(
                    new Reference($id),
                    isset($attributes['file']) ? $attributes['file'] : null,
                ));
            }
        }
    }

}
