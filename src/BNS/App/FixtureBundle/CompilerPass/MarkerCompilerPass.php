<?php

namespace BNS\App\FixtureBundle\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MarkerCompilerPass implements CompilerPassInterface
{
	public function process(ContainerBuilder $container)
    {
        $definitionName = 'fixture.marker_manager';
        if (!$container->hasDefinition($definitionName)) {
            return;
        }

        $definition = $container->getDefinition($definitionName);
        $taggedServices = $container->findTaggedServiceIds(
            'fixture.marker'
        );
        
        foreach ($taggedServices as $id => $attributes) {
            $definition->addMethodCall('addMarker', array(
                new Reference($id)
            ));
        }
    }
}