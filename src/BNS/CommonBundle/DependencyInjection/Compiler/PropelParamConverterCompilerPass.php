<?php
namespace BNS\CommonBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PropelParamConverterCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        // remove the router from the propel param converter to disable guessing of route options
        // @see PropelParamConverter L96
        if ($container->hasDefinition('propel.converter.propel.orm')) {
            $definition = $container->getDefinition('propel.converter.propel.orm');
            $definition->removeMethodCall('setRouter');
        }
    }

}
