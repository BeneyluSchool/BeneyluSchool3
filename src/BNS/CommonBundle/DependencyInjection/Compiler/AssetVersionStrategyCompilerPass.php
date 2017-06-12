<?php
namespace BNS\CommonBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class AssetVersionStrategyCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('assets.static_version_strategy') && $container->hasDefinition('bns_core.assets_redis_version_strategy')) {
            $container->setDefinition('assets.static_version_strategy', $container->getDefinition('bns_core.assets_redis_version_strategy'));
        }
    }

}
