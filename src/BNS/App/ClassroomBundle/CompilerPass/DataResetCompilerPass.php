<?php

namespace BNS\App\ClassroomBundle\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class DataResetCompilerPass implements CompilerPassInterface
{
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
	public function process(ContainerBuilder $container)
    {
        $definitionName = 'bns.data_reset.manager';
        if (!$container->hasDefinition($definitionName)) {
            return;
        }

        $definition = $container->getDefinition($definitionName);

        // DataReset
        $taggedServices = $container->findTaggedServiceIds(
            'bns.data_reset'
        );

        foreach ($taggedServices as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $definition->addMethodCall('addDataReset', array(
                    new Reference($id),
                    $attributes['type'],
                    $attributes['hasOptions']
                ));
            }
        }

        // DataResetUser
        $taggedServices = $container->findTaggedServiceIds(
            'bns.data_reset.user'
        );

        foreach ($taggedServices as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $definition->addMethodCall('addDataResetUser', array(
                    new Reference($id),
                    $attributes['type']
                ));
            }
        }
    }
}